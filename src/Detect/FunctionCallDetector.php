<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\FindingCollector;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;

/**
 * Strict mode: calls to known impure functions, matched case-insensitively as
 * PHP function names are (leading "\" ignored), and the constructs that
 * stand for one.
 */
class FunctionCallDetector implements Detector
{
    protected const INPUT = false;
    protected const OUTPUT = true;

    protected const STDOUT = [Category::STANDARD_OUTPUT, self::OUTPUT, 'writes to standard output'];
    protected const FILE_READ = [Category::FILE, self::INPUT, 'reads from file'];
    protected const FILE_WRITE = [Category::FILE, self::OUTPUT, 'writes to file'];
    protected const TIME = [Category::TIME, self::INPUT, 'reads system time'];
    protected const RANDOM_READ = [Category::RANDOM, self::INPUT, 'reads from random number generator'];
    protected const RANDOM_SEED = [Category::RANDOM, self::OUTPUT, 'writes to random number generator state'];
    protected const FILE_SYSTEM = [Category::FILE_SYSTEM, self::INPUT, 'reads from file system'];
    protected const FILE_SYSTEM_WRITE = [Category::FILE_SYSTEM, self::OUTPUT, 'writes to file system'];
    protected const HEADERS = [Category::HTTP_HEADERS, self::OUTPUT, 'writes HTTP headers'];
    protected const ERROR_LOG = [Category::ERROR_LOG, self::OUTPUT, 'writes to error log'];
    protected const SESSION_READ = [Category::SESSION, self::INPUT, 'reads session state'];
    protected const SESSION_WRITE = [Category::SESSION, self::OUTPUT, 'writes to session state'];
    protected const NETWORK_READ = [Category::NETWORK, self::INPUT, 'reads from network'];
    protected const NETWORK = [self::NETWORK_READ, [Category::NETWORK, self::OUTPUT, 'writes to network']];
    protected const PROCESS = [
        [Category::PROCESS, self::INPUT, 'reads from external process'],
        [Category::PROCESS, self::OUTPUT, 'runs external process'],
    ];
    protected const CONFIG_READ = [Category::RUNTIME_CONFIG, self::INPUT, 'reads runtime configuration'];
    protected const CONFIG_WRITE = [Category::RUNTIME_CONFIG, self::OUTPUT, 'writes runtime configuration'];
    protected const DATABASE = [
        [Category::DATABASE, self::INPUT, 'reads from database'],
        [Category::DATABASE, self::OUTPUT, 'writes to database'],
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
        'ob_' => [self::STDOUT],
    ];

    /**
     * Function name => the findings a call reports, each as [category, is
     * output, description prefix].
     *
     * echo, print, exit and die are handled by their own detectors.
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
        'gethostbyname' => [self::NETWORK_READ],
        'gethostbynamel' => [self::NETWORK_READ],
        'dns_get_record' => [self::NETWORK_READ],
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
        'date_default_timezone_set' => [self::CONFIG_WRITE],
        'setlocale' => [self::CONFIG_WRITE],
        'define' => [self::CONFIG_WRITE],
        'register_shutdown_function' => [self::CONFIG_WRITE],
        'ini_get' => [self::CONFIG_READ],
        'date_default_timezone_get' => [self::CONFIG_READ],
        'filter_input' => [[Category::SUPERGLOBAL, self::INPUT, 'reads from superglobals']],
        'filter_input_array' => [[Category::SUPERGLOBAL, self::INPUT, 'reads from superglobals']],
    ];

    public function detect(Node $node, bool $isWrite, FindingCollector $findings): void
    {
        $name = $this->calledName($node);
        if ($name === null) {
            return;
        }

        $lowerName = strtolower($name);
        if ($node instanceof Expr\FuncCall && $lowerName === 'fopen') {
            $this->detectFopen($node, $name, $findings);

            return;
        }

        foreach ($this->entries($node, $lowerName) as [$category, $isOutput, $prefix]) {
            $description = $prefix . ' (' . $name . ')';
            if ($isOutput) {
                $findings->output($description, $category, $node);
                continue;
            }
            $findings->input($description, $category, $node);
        }
    }

    /**
     * The function a node calls: a call by name, a backtick expression, which
     * calls shell_exec, or an include construct, named for itself.
     */
    protected function calledName(Node $node): ?string
    {
        if ($node instanceof Expr\ShellExec) {
            return 'shell_exec';
        }
        if ($node instanceof Expr\Include_) {
            return self::INCLUDES[$node->type];
        }
        if ($node instanceof Expr\FuncCall && $node->name instanceof Node\Name) {
            return $node->name->toString();
        }

        return null;
    }

    /**
     * The catalogue's findings, after the rules that depend on a call's
     * arguments.
     *
     * @return list<array{string, bool, string}>
     */
    protected function entries(Node $node, string $lowerName): array
    {
        if (!$node instanceof Expr\FuncCall) {
            return $this->catalogueEntries($lowerName);
        }

        $arguments = new CallArguments($node);

        return match ($lowerName) {
            'print_r', 'var_export' => $arguments->isTrue(1, 'return') ? [] : [self::STDOUT],
            'error_reporting' => $arguments->isEmpty() ? [self::CONFIG_READ] : [self::CONFIG_WRITE],
            'date_create', 'date_create_immutable' => $arguments->readsClock() ? [self::TIME] : [],
            'date', 'gmdate', 'idate' => $arguments->omits(1, 'timestamp') ? [self::TIME] : [],
            'getdate', 'localtime' => $arguments->omits(0, 'timestamp') ? [self::TIME] : [],
            'mktime', 'gmmktime' => $this->omitsDateField($arguments) ? [self::TIME] : [],
            default => $this->catalogueEntries($lowerName),
        };
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

    protected function detectFopen(Expr\FuncCall $node, string $name, FindingCollector $findings): void
    {
        $mode = $node->args[1]->value ?? null;
        if (!$mode instanceof Scalar\String_) {
            $findings->input('reads from file (' . $name . ')', Category::FILE, $node);

            return;
        }

        $mode = strtolower($mode->value);
        if (str_contains($mode, '+')) {
            $findings->input('reads from file (' . $name . ')', Category::FILE, $node);
            $findings->output('writes to file (' . $name . ')', Category::FILE, $node);

            return;
        }

        if (in_array(substr($mode, 0, 1), ['w', 'a', 'c', 'x'], true)) {
            $findings->output('writes to file (' . $name . ')', Category::FILE, $node);

            return;
        }

        $findings->input('reads from file (' . $name . ')', Category::FILE, $node);
    }
}
