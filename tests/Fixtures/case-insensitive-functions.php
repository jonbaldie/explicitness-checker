<?php

function writes_uppercase(): void
{
    VAR_DUMP([1, 2]);
}

function reads_time_mixed_case(): void
{
    $now = Time();
    echo $now;
}

function reads_random_mixed_case(): void
{
    $number = Rand(1, 10);
    echo $number;
}

function reads_file_mixed_case(): void
{
    $stream = FOPEN('php://memory', 'r');
    var_dump($stream);
}
