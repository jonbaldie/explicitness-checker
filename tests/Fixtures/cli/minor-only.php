<?php

declare(strict_types=1);

// Under --strict the only finding is standard output: Minor, exit code 1.
function greet(string $name): void
{
    echo 'Hello, ' . $name;
}

function declaresButNeverUsesGlobal(): int
{
    global $unused;

    return 1;
}
