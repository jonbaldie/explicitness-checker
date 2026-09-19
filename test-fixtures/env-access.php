<?php

// Regression fixture for #7: $_ENV access is environment access, so it is
// Critical (exit code 3), not Serious (exit code 2).
// Also used for #4 (include/exclude patterns containing "/").

function readApiKey(): string
{
    return $_ENV['API_KEY'];
}

function writeAppEnv(): void
{
    $_ENV['APP_ENV'] = 'production';
}
