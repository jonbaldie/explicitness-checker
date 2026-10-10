<?php

function iife(string $path): void
{
    (unlink(...))($path);
}

function string_call(string $path): void
{
    ('unlink')($path);
}

function via_call_user_func(string $path): mixed
{
    return call_user_func('unlink', $path);
}

function string_read(string $path): string|false
{
    return ('file_get_contents')($path);
}

function call_user_func_fopen(string $path): mixed
{
    return call_user_func('fopen', $path, 'w');
}

function iife_exit_message(): void
{
    (exit(...))('bye');
}

function variable_callee(callable $fn, string $path): void
{
    ($fn)($path);
    call_user_func($fn, $path);
}

function callback(array $paths): array
{
    return array_map(unlink(...), $paths);
}

function stored(): Closure
{
    return unlink(...);
}
