<?php

declare(strict_types=1);

/**
 * strict_examples.php.
 *
 * This file contains examples that should be flagged by the --strict mode
 * of the explicitness checker. These are not necessarily "bad" in all
 * contexts, but they represent implicit inputs and outputs according to
 * a stricter, more functional definition.
 */

// --- Example 1: Object Property Access ---

class UserSession
{
    private string $username;

    private int $loginCount = 0;

    public function __construct(string $username)
    {
        $this->username = $username; // Implicit output (write to property)
    }

    /**
     * Implicit input: reads $this->username
     * Implicit output: writes to standard output (echo).
     */
    public function greet(): void
    {
        echo 'Hello, '.$this->username; // Implicit input and output
    }

    /**
     * Implicit input: reads $this->loginCount
     * Implicit output: writes to $this->loginCount.
     */
    public function incrementLoginCount(): int
    {
        $this->loginCount++; // Reads and writes $this->loginCount

        return $this->loginCount;
    }
}

// --- Example 2: Static Property Access ---

class AppAnalytics
{
    public static int $pageViews = 0;

    private static array $events = [];

    /**
     * Implicit output: writes to self::$pageViews.
     */
    public static function recordPageView(): void
    {
        self::$pageViews++;
    }

    /**
     * Implicit input: reads self::$pageViews
     * Implicit output: writes to self::$events.
     */
    public static function logEvent(string $eventName): void
    {
        $currentViews = self::$pageViews; // Implicit input
        self::$events[] = [
            // Implicit output
            'name' => $eventName,
            'views_at_time' => $currentViews,
        ];
    }
}

// --- Example 3: Output Functions ---

/**
 * Implicit output: writes to standard output (var_dump).
 */
function debug_user_data(array $user): void
{
    var_dump($user);
}

/**
 * Implicit output: writes to standard output (print_r) and terminates.
 */
function inspect_and_die(array $data): void
{
    print_r($data);
    exit('Execution stopped.');
}

// --- Example 4: Environment Variables ---

/**
 * Implicit input: reads from environment variables.
 */
function get_database_url(): string
{
    return getenv('DATABASE_URL') ?: 'localhost'; // Implicit input
}

/**
 * Implicit input: reads from $_ENV superglobal.
 * Implicit output: writes to environment variables.
 */
function configure_environment(): void
{
    $debug = $_ENV['DEBUG'] ?? 'false'; // Implicit input
    putenv('APP_ENV=production'); // Implicit output
}

// --- Example 5: Time Functions ---

/**
 * Implicit input: reads system time (non-deterministic).
 */
function get_current_timestamp(): int
{
    return time(); // Implicit input - system time
}

/**
 * Implicit input: reads system time (non-deterministic).
 */
function format_current_date(): string
{
    return date('Y-m-d H:i:s'); // Implicit input - system time
}

/**
 * Implicit input: reads high-precision system time.
 */
function benchmark_operation(): float
{
    $start = microtime(true); // Implicit input
    // ... some operation ...
    $end = microtime(true); // Implicit input

    return $end - $start;
}

// --- Example 6: Random Functions ---

/**
 * Implicit input: reads from random number generator.
 */
function generate_random_id(): int
{
    return rand(1000, 9999); // Implicit input - random state
}

/**
 * Implicit input: reads from random number generator.
 */
function create_secure_token(): string
{
    $bytes = random_bytes(16); // Implicit input - random state

    return bin2hex($bytes);
}

/**
 * Implicit output: writes to random number generator state.
 */
function seed_random_generator(): void
{
    mt_srand(12345); // Implicit output - changes RNG state
}

// --- Example 7: File System Queries ---

/**
 * Implicit input: reads from file system state.
 */
function check_config_file(): bool
{
    return file_exists('/etc/app.conf'); // Implicit input
}

/**
 * Implicit input: reads from file system state.
 */
function get_file_info(string $path): array
{
    return [
        'exists' => file_exists($path), // Implicit input
        'is_file' => is_file($path), // Implicit input
        'size' => filesize($path), // Implicit input
        'modified' => filemtime($path), // Implicit input
    ];
}

/**
 * Implicit input: reads directory contents from file system.
 */
function list_config_files(): array
{
    return glob('/etc/*.conf'); // Implicit input
}

// --- Example 8: HTTP Headers ---

/**
 * Implicit output: writes HTTP headers.
 */
function send_json_response(array $data): void
{
    header('Content-Type: application/json'); // Implicit output
    echo json_encode($data);
}

/**
 * Implicit output: writes HTTP cookies and headers.
 */
function set_user_preferences(string $theme): void
{
    setcookie('theme', $theme, time() + 3600); // Implicit output
    header('Cache-Control: no-cache'); // Implicit output
}

// --- Example 9: Error Logging ---

/**
 * Implicit output: writes to error log.
 */
function log_user_action(string $action): void
{
    error_log("User performed: {$action}"); // Implicit output
}

/**
 * Implicit output: triggers error with side effects.
 */
function validate_input(string $input): bool
{
    if (empty($input)) {
        trigger_error('Input cannot be empty', E_USER_WARNING); // Implicit output

        return false;
    }

    return true;
}

// --- Example 10: Session Functions ---

/**
 * Implicit output: starts session (changes global state).
 */
function initialize_user_session(): void
{
    session_start(); // Implicit output
}

/**
 * Implicit input: reads session state.
 */
function get_session_info(): array
{
    return [
        'id' => session_id(), // Implicit input
        'name' => session_name(), // Implicit input
    ];
}

/**
 * Implicit output: destroys session state.
 */
function logout_user(): void
{
    session_destroy(); // Implicit output
}
