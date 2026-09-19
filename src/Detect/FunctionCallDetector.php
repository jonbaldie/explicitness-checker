<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\FindingCollector;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Strict mode: calls to known impure functions, matched by their literal name
 * as written (case-sensitive, leading "\" ignored).
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
    protected const HEADERS = [Category::HTTP_HEADERS, self::OUTPUT, 'writes HTTP headers'];
    protected const ERROR_LOG = [Category::ERROR_LOG, self::OUTPUT, 'writes to error log'];
    protected const SESSION_READ = [Category::SESSION, self::INPUT, 'reads session state'];
    protected const SESSION_WRITE = [Category::SESSION, self::OUTPUT, 'writes to session state'];

    /**
     * Function name => [category, is output, description prefix].
     *
     * echo and print are language constructs, handled by
     * LanguageConstructDetector. So are exit and die, but `\exit()` and
     * `\die()` parse as function calls, and are real functions since PHP 8.4.
     */
    protected const CATALOGUE = [
        'printf' => self::STDOUT,
        'vprintf' => self::STDOUT,
        'var_dump' => self::STDOUT,
        'var_export' => self::STDOUT,
        'print_r' => self::STDOUT,
        'die' => self::STDOUT,
        'exit' => self::STDOUT,
        'fwrite' => self::FILE_WRITE,
        'file_put_contents' => self::FILE_WRITE,
        'file_get_contents' => self::FILE_READ,
        'fread' => self::FILE_READ,
        'fopen' => self::FILE_READ,
        'getenv' => [Category::ENVIRONMENT, self::INPUT, 'reads from environment variables'],
        'putenv' => [Category::ENVIRONMENT, self::OUTPUT, 'writes to environment variables'],
        'time' => self::TIME,
        'date' => self::TIME,
        'microtime' => self::TIME,
        'gettimeofday' => self::TIME,
        'rand' => self::RANDOM_READ,
        'mt_rand' => self::RANDOM_READ,
        'random_int' => self::RANDOM_READ,
        'random_bytes' => self::RANDOM_READ,
        'srand' => self::RANDOM_SEED,
        'mt_srand' => self::RANDOM_SEED,
        'file_exists' => self::FILE_SYSTEM,
        'is_file' => self::FILE_SYSTEM,
        'is_dir' => self::FILE_SYSTEM,
        'filesize' => self::FILE_SYSTEM,
        'filemtime' => self::FILE_SYSTEM,
        'is_readable' => self::FILE_SYSTEM,
        'is_writable' => self::FILE_SYSTEM,
        'scandir' => self::FILE_SYSTEM,
        'glob' => self::FILE_SYSTEM,
        'header' => self::HEADERS,
        'setcookie' => self::HEADERS,
        'setrawcookie' => self::HEADERS,
        'http_response_code' => self::HEADERS,
        'error_log' => self::ERROR_LOG,
        'trigger_error' => self::ERROR_LOG,
        'user_error' => self::ERROR_LOG,
        'session_start' => self::SESSION_WRITE,
        'session_destroy' => self::SESSION_WRITE,
        'session_regenerate_id' => self::SESSION_WRITE,
        'session_write_close' => self::SESSION_WRITE,
        'session_id' => self::SESSION_READ,
        'session_name' => self::SESSION_READ,
    ];

    public function detect(Node $node, bool $isWrite, FindingCollector $findings): void
    {
        if (!$node instanceof Expr\FuncCall || !$node->name instanceof Node\Name) {
            return;
        }

        $name = $node->name->toString();
        if (!isset(self::CATALOGUE[$name])) {
            return;
        }

        [$category, $isOutput, $prefix] = self::CATALOGUE[$name];
        $description = $prefix . ' (' . $name . ')';
        if ($isOutput) {
            $findings->output($description, $category, $node);

            return;
        }

        $findings->input($description, $category, $node);
    }
}
