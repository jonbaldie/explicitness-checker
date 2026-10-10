<?php

function pipe_unlink(string $path): void
{
    $path |> unlink(...);
}

function pipe_read(string $path): int
{
    return $path |> file_get_contents(...) |> strlen(...);
}

function pipe_exit(int $code): void
{
    $code |> exit(...);
}

function pipe_exit_message(): void
{
    'bye' |> exit(...);
}

function pipe_fopen(string $path): mixed
{
    return $path |> fopen(...);
}

function pipe_error_reporting(int $level): int
{
    return $level |> error_reporting(...);
}

function pipe_closure(string $path): string
{
    return $path |> (fn (string $value): string => $value);
}

function stored(): Closure
{
    return unlink(...);
}
