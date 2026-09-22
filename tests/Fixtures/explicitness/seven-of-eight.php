<?php

// 8 checked function-likes, 7 of them explicit: 87.5% explicit (#64). The one
// with implicit I/O has two findings and still counts once.

function identity(mixed $value): mixed
{
    return $value;
}

class Doubler
{
    public function double(int $value): int
    {
        return $value * 2;
    }

    public static function name(): string
    {
        return 'doubler';
    }
}

function incrementer(): callable
{
    return fn (int $value): int => $value + 1;
}

function copies(array $values): array
{
    return array_map(function (mixed $value): mixed {
        return $value;
    }, $values);
}

function readsAndWritesEnvironment(): string
{
    $_ENV['APP_ENV'] = 'production';

    return $_ENV['API_KEY'];
}
