<?php

declare(strict_types=1);

/**
 * good_examples.php.
 *
 * This file contains intentionally "good" (explicit) examples that follow the
 * principle: all inputs are provided via arguments and all outputs are returned.
 *
 * None of these examples read or write global variables, superglobals, or
 * implicit state. Callers are responsible for providing and receiving state.
 */

/**
 * Pure function: adds two numbers and returns the result.
 *
 * Explicit inputs: $a, $b
 * Explicit output: int sum
 */
function add(int $a, int $b): int
{
    return $a + $b;
}

/**
 * Pure function: maps an array of integers to their squares.
 *
 * Explicit input: array $items
 * Explicit output: array (new array with squared values)
 */
function squareAll(array $items): array
{
    $out = [];
    foreach ($items as $v) {
        $out[] = $v * $v;
    }

    return $out;
}

/**
 * Explicit state transition: updates a "session" associative array and returns
 * the new session instead of mutating any global session store.
 *
 * Explicit input: array $session, string $user
 * Explicit output: array $newSession
 */
function loginUser(array $session, string $user): array
{
    // Do not mutate $session in place: produce a new array to be explicit.
    $newSession = $session;
    $newSession['user'] = $user;
    $newSession['last_login'] = (new DateTimeImmutable)->format(DateTime::ATOM);

    return $newSession;
}

/**
 * Explicit configuration usage: a service that depends on a configuration
 * array is passed the configuration rather than reading it from globals.
 *
 * Explicit input: array $config, string $input
 * Explicit output: string (result)
 */
function processWithConfig(array $config, string $input): string
{
    $prefix = $config['prefix'] ?? '';
    $mode = $config['mode'] ?? 'default';

    // Deterministic processing based only on inputs
    return sprintf('%s[%s] %s', strtoupper($mode), $prefix, $input);
}

/**
 * Example of using a callable dependency explicitly passed in. The callable is
 * invoked and its return value is used; no global logger is touched.
 *
 * Explicit input: callable $logger, string $message
 * Explicit output: string (logged message)
 */
function callLogger(callable $logger, string $message): string
{
    // The logger callable returns a string (for example, a formatted message).
    $logged = $logger($message);

    return $logged;
}

/**
 * Small class that follows explicitness: its methods operate only on arguments
 * and return results. No side-effects or global state access.
 */
class Calculator
{
    /**
     * Adds two floats and returns the sum.
     *
     * Explicit input: float $x, float $y
     * Explicit output: float
     */
    public function add(float $x, float $y): float
    {
        return $x + $y;
    }

    /**
     * Multiplies each element of $values by $factor and returns a new array.
     *
     * Explicit inputs: array $values, float $factor
     * Explicit output: array
     */
    public function scale(array $values, float $factor): array
    {
        $out = [];
        foreach ($values as $v) {
            $out[] = $v * $factor;
        }

        return $out;
    }
}

/**
 * Demonstrates composing explicit functions. Nothing here reads or writes
 * global state; the caller composes the inputs and handles the outputs.
 */
function composeExample(): array
{
    $numbers = [1, 2, 3, 4];
    $squares = squareAll($numbers);               // explicit
    $calc = new Calculator;
    $scaled = $calc->scale($squares, 0.5);       // explicit

    $config = ['prefix' => '>>', 'mode' => 'prod'];
    $processed = processWithConfig($config, 'payload'); // explicit

    // return all results so the caller can inspect or persist them
    return [
        'original' => $numbers,
        'squares' => $squares,
        'scaled' => $scaled,
        'processed' => $processed,
    ];
}

/**
 * Anonymous function example: closure does not capture any external implicit
 * state; the value it needs is passed explicitly when invoked.
 */
$adder = function (int $a, int $b): int {
    return $a + $b;
};

/*
 * End of good examples.
 *
 * Notes for usage:
 * - To "persist" session/state, the caller receives the returned array and is
 *   responsible for storing it (e.g. in a database or an application-managed store).
 * - Avoid using globals or superglobals inside functions; pass everything in
 *   and return everything out to remain explicit and deterministic.
 */
