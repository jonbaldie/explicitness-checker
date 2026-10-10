<?php

function dynamic(string $class): int
{
    return $class::now();
}

function parenthesized(string $class): int
{
    return ($class)::now();
}

function dynamic_method(): int
{
    $name = 'now';

    return Clock::{$name}();
}

class Clock
{
    public static function now(): int
    {
        return 1;
    }
}
