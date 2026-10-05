<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\FindingCollector;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Strict mode: the PHP built-ins that perform side effects or read ambient
 * state. These are calls to known impure functions, matched
 * case-insensitively as PHP function names are (leading "\" ignored); the
 * language constructs echo, print, exit, die, include, require and backticks;
 * and creating an object that reads the clock or the default random engine,
 * matched by its resolved class name in any case.
 *
 * Every rule that depends on a call's arguments reads them through
 * CallArguments, so a parameter is found by position or by name.
 */
class BuiltinCatalogueDetector implements Detector
{
    protected const INPUT = false;
    protected const OUTPUT = true;

    protected const STDOUT = [Category::STANDARD_OUTPUT, self::OUTPUT, 'writes to standard output'];
    protected const TERMINATES = [Category::STANDARD_OUTPUT, self::OUTPUT, 'terminates the program'];
    protected const FILE_READ = [Category::FILE, self::INPUT, 'reads from file'];
    protected const FILE_WRITE = [Category::FILE, self::OUTPUT, 'writes to file'];
    protected const TIME = [Category::TIME, self::INPUT, 'reads system time'];
    protected const RANDOM_READ = [Category::RANDOM, self::INPUT, 'reads from random number generator'];
    protected const RANDOM_SEED = [Category::RANDOM, self::OUTPUT, 'writes to random number generator state'];
    protected const FILE_SYSTEM = [Category::FILE_SYSTEM, self::INPUT, 'reads from file system'];
    protected const FILE_SYSTEM_WRITE = [Category::FILE_SYSTEM, self::OUTPUT, 'writes to file system'];
    protected const HEADERS = [Category::HTTP_HEADERS, self::OUTPUT, 'writes HTTP headers'];
    protected const HEADERS_READ = [Category::HTTP_HEADERS, self::INPUT, 'reads HTTP headers'];
    protected const ERROR_LOG = [Category::ERROR_LOG, self::OUTPUT, 'writes to error log'];
    protected const SESSION_READ = [Category::SESSION, self::INPUT, 'reads session state'];
    protected const SESSION_WRITE = [Category::SESSION, self::OUTPUT, 'writes to session state'];
    protected const NETWORK_READ = [Category::NETWORK, self::INPUT, 'reads from network'];
    protected const NETWORK = [self::NETWORK_READ, [Category::NETWORK, self::OUTPUT, 'writes to network']];
    protected const PROCESS = [
        [Category::PROCESS, self::INPUT, 'reads from external process'],
        [Category::PROCESS, self::OUTPUT, 'runs external process'],
    ];
    protected const SUPERGLOBAL_READ = [Category::SUPERGLOBAL, self::INPUT, 'reads from superglobals'];
    protected const CONFIG_READ = [Category::RUNTIME_CONFIG, self::INPUT, 'reads runtime configuration'];
    protected const CONFIG_WRITE = [Category::RUNTIME_CONFIG, self::OUTPUT, 'writes runtime configuration'];
    protected const DATABASE = [
        [Category::DATABASE, self::INPUT, 'reads from database'],
        [Category::DATABASE, self::OUTPUT, 'writes to database'],
    ];

    /**
     * The constructs PHP also accepts as functions.
     */
    protected const EXIT_NAMES = ['exit', 'die'];

    /**
     * Output construct => its name.
     */
    protected const OUTPUT_CONSTRUCTS = [
        Stmt\Echo_::class => 'echo',
        Expr\Print_::class => 'print',
    ];

    /**
     * Include construct type => its name.
     */
    protected const INCLUDES = [
        Expr\Include_::TYPE_INCLUDE => 'include',
        Expr\Include_::TYPE_INCLUDE_ONCE => 'include_once',
        Expr\Include_::TYPE_REQUIRE => 'require',
        Expr\Include_::TYPE_REQUIRE_ONCE => 'require_once',
    ];

    /**
     * mktime's parameters, in order.
     */
    protected const DATE_FIELDS = ['hour', 'minute', 'second', 'month', 'day', 'year'];

    /**
     * Name prefix => the findings a call to any function with that prefix
     * reports, for the families too large to list.
     */
    protected const PREFIXES = [
        'mysqli_' => self::DATABASE,
        'pg_' => self::DATABASE,
        'odbc_' => self::DATABASE,
        'sqlsrv_' => self::DATABASE,
        'oci_' => self::DATABASE,
        'socket_' => self::NETWORK,
        'ftp_' => self::NETWORK,
        'ob_' => [self::STDOUT],
    ];

    /**
     * Function name => the findings a call reports, each as [category, is
     * output, description prefix].
     *
     * The rules that depend on a call's arguments are in callEntries().
     */
    protected const CATALOGUE = [
        'printf' => [self::STDOUT],
        'vprintf' => [self::STDOUT],
        'var_dump' => [self::STDOUT],
        'flush' => [self::STDOUT],
        'fwrite' => [self::FILE_WRITE],
        'file_put_contents' => [self::FILE_WRITE],
        'file_get_contents' => [self::FILE_READ],
        'fread' => [self::FILE_READ],
        'fgets' => [self::FILE_READ],
        'readline' => [self::FILE_READ],
        'fgetc' => [self::FILE_READ],
        'fgetcsv' => [self::FILE_READ],
        'fscanf' => [self::FILE_READ],
        'file' => [self::FILE_READ],
        'stream_get_contents' => [self::FILE_READ],
        'fputs' => [self::FILE_WRITE],
        'fputcsv' => [self::FILE_WRITE],
        'fprintf' => [self::FILE_WRITE],
        'vfprintf' => [self::FILE_WRITE],
        'readfile' => [self::FILE_READ, self::STDOUT],
        'fpassthru' => [self::FILE_READ, self::STDOUT],
        'getenv' => [[Category::ENVIRONMENT, self::INPUT, 'reads from environment variables']],
        'putenv' => [[Category::ENVIRONMENT, self::OUTPUT, 'writes to environment variables']],
        'time' => [self::TIME],
        'microtime' => [self::TIME],
        'gettimeofday' => [self::TIME],
        'hrtime' => [self::TIME],
        'rand' => [self::RANDOM_READ],
        'mt_rand' => [self::RANDOM_READ],
        'random_int' => [self::RANDOM_READ],
        'random_bytes' => [self::RANDOM_READ],
        'shuffle' => [self::RANDOM_READ],
        'array_rand' => [self::RANDOM_READ],
        'str_shuffle' => [self::RANDOM_READ],
        'lcg_value' => [self::RANDOM_READ],
        'uniqid' => [self::RANDOM_READ],
        'srand' => [self::RANDOM_SEED],
        'mt_srand' => [self::RANDOM_SEED],
        'file_exists' => [self::FILE_SYSTEM],
        'is_file' => [self::FILE_SYSTEM],
        'is_dir' => [self::FILE_SYSTEM],
        'filesize' => [self::FILE_SYSTEM],
        'filemtime' => [self::FILE_SYSTEM],
        'is_readable' => [self::FILE_SYSTEM],
        'is_writable' => [self::FILE_SYSTEM],
        'scandir' => [self::FILE_SYSTEM],
        'glob' => [self::FILE_SYSTEM],
        'unlink' => [self::FILE_SYSTEM_WRITE],
        'rename' => [self::FILE_SYSTEM_WRITE],
        'mkdir' => [self::FILE_SYSTEM_WRITE],
        'rmdir' => [self::FILE_SYSTEM_WRITE],
        'copy' => [self::FILE_SYSTEM_WRITE],
        'touch' => [self::FILE_SYSTEM_WRITE],
        'chmod' => [self::FILE_SYSTEM_WRITE],
        'tmpfile' => [self::FILE_SYSTEM_WRITE],
        'tempnam' => [self::FILE_SYSTEM_WRITE],
        'header' => [self::HEADERS],
        'setcookie' => [self::HEADERS],
        'setrawcookie' => [self::HEADERS],
        'http_response_code' => [self::HEADERS],
        'getallheaders' => [self::HEADERS_READ],
        'apache_request_headers' => [self::HEADERS_READ],
        'error_log' => [self::ERROR_LOG],
        'trigger_error' => [self::ERROR_LOG],
        'user_error' => [self::ERROR_LOG],
        'syslog' => [self::ERROR_LOG],
        'session_start' => [self::SESSION_WRITE],
        'session_destroy' => [self::SESSION_WRITE],
        'session_regenerate_id' => [self::SESSION_WRITE],
        'session_write_close' => [self::SESSION_WRITE],
        'session_id' => [self::SESSION_READ],
        'session_name' => [self::SESSION_READ],
        'curl_exec' => self::NETWORK,
        'curl_multi_exec' => self::NETWORK,
        'fsockopen' => self::NETWORK,
        'pfsockopen' => self::NETWORK,
        'stream_socket_client' => self::NETWORK,
        'get_headers' => self::NETWORK,
        'gethostbyname' => [self::NETWORK_READ],
        'gethostbynamel' => [self::NETWORK_READ],
        'dns_get_record' => [self::NETWORK_READ],
        'gethostbyaddr' => [self::NETWORK_READ],
        'checkdnsrr' => [self::NETWORK_READ],
        'dns_check_record' => [self::NETWORK_READ],
        'exec' => self::PROCESS,
        'shell_exec' => self::PROCESS,
        'system' => self::PROCESS,
        'passthru' => self::PROCESS,
        'proc_open' => self::PROCESS,
        'popen' => self::PROCESS,
        'mail' => [[Category::MAIL, self::OUTPUT, 'sends email']],
        'mb_send_mail' => [[Category::MAIL, self::OUTPUT, 'sends email']],
        'include' => [[Category::INCLUDE, self::INPUT, 'includes file']],
        'include_once' => [[Category::INCLUDE, self::INPUT, 'includes file']],
        'require' => [[Category::INCLUDE, self::INPUT, 'includes file']],
        'require_once' => [[Category::INCLUDE, self::INPUT, 'includes file']],
        'ini_set' => [self::CONFIG_WRITE],
        'set_error_handler' => [self::CONFIG_WRITE],
        'set_exception_handler' => [self::CONFIG_WRITE],
        'ini_alter' => [self::CONFIG_WRITE],
        'restore_error_handler' => [self::CONFIG_WRITE],
        'restore_exception_handler' => [self::CONFIG_WRITE],
        'date_default_timezone_set' => [self::CONFIG_WRITE],
        'setlocale' => [self::CONFIG_WRITE],
        'define' => [self::CONFIG_WRITE],
        'register_shutdown_function' => [self::CONFIG_WRITE],
        'set_time_limit' => [self::CONFIG_WRITE],
        'ini_get' => [self::CONFIG_READ],
        'date_default_timezone_get' => [self::CONFIG_READ],
        'filter_input' => [self::SUPERGLOBAL_READ],
        'filter_input_array' => [self::SUPERGLOBAL_READ],
        'getopt' => [self::SUPERGLOBAL_READ],
    ];

    public function detect(Node $node, bool $isWrite, FindingCollector $findings): void
    {
        $classified = $this->classify($node);
        if ($classified === null) {
            return;
        }

        [$name, $entries] = $classified;
        foreach ($entries as [$category, $isOutput, $prefix]) {
            $description = $prefix . ' (' . $name . ')';
            if ($isOutput) {
                $findings->output($description, $category, $node);
                continue;
            }
            $findings->input($description, $category, $node);
        }
    }

    /**
     * The name a node's findings are described by, and those findings, or
     * null for a node the catalogue does not cover.
     *
     * @return array{string, list<array{string, bool, string}>}|null
     */
    protected function classify(Node $node): ?array
    {
        return $this->classifyConstruct($node) ?? $this->classifyNamedInvocation($node);
    }

    /**
     * @return array{string, list<array{string, bool, string}>}|null
     */
    protected function classifyConstruct(Node $node): ?array
    {
        foreach (self::OUTPUT_CONSTRUCTS as $class => $construct) {
            if ($node instanceof $class) {
                return [$construct, [self::STDOUT]];
            }
        }
        if ($node instanceof Expr\Exit_) {
            return [$this->exitName($node), $this->exitEntries(new CallArguments($node))];
        }
        if ($node instanceof Expr\ShellExec) {
            return ['shell_exec', $this->catalogueEntries('shell_exec')];
        }
        if ($node instanceof Expr\Include_) {
            $name = self::INCLUDES[$node->type];

            return [$name, $this->catalogueEntries($name)];
        }

        return null;
    }

    /**
     * A call by name, or a `new` of a named class.
     *
     * @return array{string, list<array{string, bool, string}>}|null
     */
    protected function classifyNamedInvocation(Node $node): ?array
    {
        if ($node instanceof Expr\New_ && $node->class instanceof Node\Name) {
            $class = $node->class->toString();

            return [$class, $this->newEntries(strtolower($class), new CallArguments($node))];
        }
        if ($node instanceof Expr\FuncCall && $node->name instanceof Node\Name && !$node->isFirstClassCallable()) {
            return $this->classifyFunction($node->name->toString(), new CallArguments($node));
        }

        return null;
    }

    /**
     * A function call. exit and die, which PHP also accepts as functions,
     * are described by their lowercase name, as the constructs are.
     *
     * @return array{string, list<array{string, bool, string}>}
     */
    protected function classifyFunction(string $name, CallArguments $arguments): array
    {
        $lowerName = strtolower($name);
        if (in_array($lowerName, self::EXIT_NAMES, true)) {
            return [$lowerName, $this->exitEntries($arguments)];
        }

        return [$name, $this->callEntries($lowerName, $arguments)];
    }

    /**
     * The catalogue's findings, after the rules that depend on a call's
     * arguments.
     *
     * @return list<array{string, bool, string}>
     */
    protected function callEntries(string $lowerName, CallArguments $arguments): array
    {
        return match ($lowerName) {
            'fopen' => $this->fopenEntries($arguments->string(1, 'mode')),
            'print_r', 'var_export' => $arguments->isTrue(1, 'return') ? [] : [self::STDOUT],
            'error_reporting' => $arguments->isEmpty() ? [self::CONFIG_READ] : [self::CONFIG_WRITE],
            'ignore_user_abort' => $arguments->omits(0, 'enable') ? [self::CONFIG_READ] : [self::CONFIG_WRITE],
            'date_create', 'date_create_immutable' => $this->readsClock($arguments) ? [self::TIME] : [],
            'date', 'gmdate', 'idate' => $arguments->omits(1, 'timestamp') ? [self::TIME] : [],
            'getdate', 'localtime' => $arguments->omits(0, 'timestamp') ? [self::TIME] : [],
            'mktime', 'gmmktime' => $this->omitsDateField($arguments) ? [self::TIME] : [],
            default => $this->catalogueEntries($lowerName),
        };
    }

    /**
     * A date reads the clock; a Randomizer given no engine reads the default
     * random engine.
     *
     * @return list<array{string, bool, string}>
     */
    protected function newEntries(string $lowerClass, CallArguments $arguments): array
    {
        return match ($lowerClass) {
            'datetime', 'datetimeimmutable' => $this->readsClock($arguments) ? [self::TIME] : [],
            'random\randomizer' => $arguments->omits(0, 'engine') ? [self::RANDOM_READ] : [],
            default => [],
        };
    }

    /**
     * exit and die terminate the process, unless their status is a string
     * literal, which PHP writes to standard output first.
     *
     * @return list<array{string, bool, string}>
     */
    protected function exitEntries(CallArguments $arguments): array
    {
        return $arguments->string(0, 'status') === null ? [self::TERMINATES] : [self::STDOUT];
    }

    protected function exitName(Expr\Exit_ $node): string
    {
        return $node->getAttribute('kind', Expr\Exit_::KIND_EXIT) === Expr\Exit_::KIND_DIE
            ? 'die'
            : 'exit';
    }

    /**
     * fopen reads a file opened with no literal mode or a read mode, writes
     * one opened to write, append, create or exclusively create, and does
     * both with "+".
     *
     * @return list<array{string, bool, string}>
     */
    protected function fopenEntries(?string $mode): array
    {
        $mode = strtolower($mode ?? 'r');
        if (str_contains($mode, '+')) {
            return [self::FILE_READ, self::FILE_WRITE];
        }
        if (in_array(substr($mode, 0, 1), ['w', 'a', 'c', 'x'], true)) {
            return [self::FILE_WRITE];
        }

        return [self::FILE_READ];
    }

    /**
     * A date is built from the clock when its datetime argument is absent,
     * null, 'now' in any case, or '', which PHP also reads as now.
     */
    protected function readsClock(CallArguments $arguments): bool
    {
        $datetime = $arguments->string(0, 'datetime');

        return $arguments->omits(0, 'datetime')
            || ($datetime !== null && in_array(strtolower($datetime), ['now', ''], true));
    }

    /**
     * mktime fills any of its six date and time fields that are missing or
     * null from the current time.
     */
    protected function omitsDateField(CallArguments $arguments): bool
    {
        foreach (self::DATE_FIELDS as $position => $name) {
            if ($arguments->omits($position, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{string, bool, string}>
     */
    protected function catalogueEntries(string $lowerName): array
    {
        if (isset(self::CATALOGUE[$lowerName])) {
            return self::CATALOGUE[$lowerName];
        }
        foreach (self::PREFIXES as $prefix => $entries) {
            if (str_starts_with($lowerName, $prefix)) {
                return $entries;
            }
        }

        return [];
    }
}
