<?php

// Two explicit function-likes and one that reads a superglobal (Serious),
// with no parse errors, so the gate alone decides a passing exit code.

function add(int $a, int $b): int
{
    return $a + $b;
}

function greet(string $name): string
{
    return "Hello, {$name}";
}

function readsPost(): mixed
{
    return $_POST['value'] ?? null;
}
