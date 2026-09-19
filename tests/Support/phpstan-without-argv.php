<?php

declare(strict_types=1);

/*
 * Runs PHPStan on the CLI script as if PHP's register_argc_argv setting were
 * off, which makes PHPStan treat $argv in global scope as possibly undefined.
 *
 * Run it with `php -d register_argc_argv=0 -d phpstan.restarted=1`. With that
 * setting off PHP doesn't fill in $_SERVER['argv'], so this script supplies
 * PHPStan's arguments itself. `phpstan.restarted=1` stops PHPStan re-executing
 * itself without the setting; `--debug` keeps it in one process.
 */

$root = dirname(__DIR__, 2);
$phpstan = $root . '/vendor/bin/phpstan';

$_SERVER['argv'] = [
    $phpstan,
    'analyse',
    '--level',
    '6',
    '--no-progress',
    '--debug',
    '--memory-limit',
    '1G',
    '--error-format',
    'raw',
    '--configuration',
    __DIR__ . '/empty.neon',
    $root . '/bin/explicitness-checker',
];
$_SERVER['argc'] = count($_SERVER['argv']);

require $root . '/vendor/phpstan/phpstan/phpstan';
