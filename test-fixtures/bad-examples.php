<?php

declare(strict_types=1);

/**
 * bad_examples.php.
 *
 * This file contains intentionally "bad" examples that exhibit implicit inputs
 * and/or outputs for testing the explicitness checker.
 *
 * Examples included:
 *  - Reading and writing a named global via `global $var`
 *  - Reading and writing via `$GLOBALS[...]`
 *  - Reading from PHP superglobals (`$_GET`, `$_POST`, `$_SESSION`)
 *  - Writing to PHP superglobals
 */

/**
 * Top-level global variables that will be referenced from functions.
 */
$some_global_number = 42;
$config = ['mode' => 'prod'];
$GLOBALS['app_name'] = 'ExplicitnessApp';

/**
 * Implicit input (reading) and implicit output (writing) via `global`.
 */
function uses_global_var(): int
{
    // implicit input: reads $some_global_number from global scope
    // implicit output: decrements the global variable without returning the change
    global $some_global_number;

    echo "Current global: $some_global_number\n";

    $some_global_number--; // implicit output (changes global)

    return $some_global_number + 1; // uses the (previously read) global value implicitly
}

/**
 * Implicit access using the $GLOBALS super-array.
 */
function uses_globals_array(): string
{
    // implicit input: reads $GLOBALS['app_name']
    // implicit output: overwrites $GLOBALS['app_name']
    $app = $GLOBALS['app_name'] ?? 'unknown';

    $GLOBALS['app_name'] = strtoupper((string) $app); // implicit output

    return $app;
}

/**
 * Reads from superglobals -> implicit inputs.
 */
function reads_superglobals(): array
{
    // implicit inputs: $_GET, $_POST
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $name = $_POST['name'] ?? 'guest';

    return ['id' => $id, 'name' => $name];
}

/**
 * Writes to superglobals -> implicit outputs.
 */
function writes_superglobals(): void
{
    // implicit output: writing to $_SESSION
    if (! isset($_SESSION)) {
        $_SESSION = [];
    }

    $_SESSION['user'] = 'alice'; // implicit output: mutating global session state
    $_COOKIE['last_action'] = 'login'; // implicit output: mutating superglobal cookie array
}

/**
 * Mixed example: reads a global config and writes to a global store.
 */
function config_and_store_change(): void
{
    global $config;

    // implicit input: read global $config
    $mode = $config['mode'] ?? 'dev';

    // implicit output: write to $GLOBALS['store']
    $key = "mode:{$mode}";
    $GLOBALS['store'][$key] = microtime(true);
}

/**
 * Uses variable variables referencing globals (dynamic access).
 * This is more dynamic and harder to analyze statically.
 */
function variable_variable_global_read(string $name)
{
    // implicit input: dynamic read from a global variable using variable variables
    // e.g. if $name === 'config', this will return the $config global
    global ${$name};

    return ${$name} ?? null;
}

/**
 * Demonstrates increment/decrement on a global without explicit return.
 */
function inc_global_counter(): void
{
    global $some_global_number;

    $some_global_number++; // implicit output
}

/**
 * Anonymous function stored in global variable (implicit behavior via closure & global).
 */
$global_closure = function (): void {
    // closure captures nothing explicitly; but will read/write $GLOBALS if used
    $val = $GLOBALS['app_name'] ?? 'none'; // implicit input
    $GLOBALS['app_name'] = $val.'-v2';    // implicit output
};

/*
 * End of fixtures.
 *
 * Note: These functions intentionally perform implicit reads/writes so that
 * the explicitness-checker can detect them during tests.
 */
