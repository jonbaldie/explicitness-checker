<?php

declare(strict_types=1);

use JonBaldie\ExplicitnessChecker\Cli\FileFilter;

require __DIR__ . '/bootstrap.php';

$ordinaryOnly = in_array('--ordinary-only', $argv, true);
$count = 0;
$failures = 0;
foreach (['ordinary', 'consumed-empty', 'consumed-stale'] as $environment) {
    if ($ordinaryOnly && $environment !== 'ordinary') {
        continue;
    }
    foreach (['include', 'exclude'] as $flag) {
        foreach (['[' => 'missing terminating ] for character class', '(' => 'missing closing parenthesis'] as $pattern => $reason) {
            error_clear_last();
            if ($environment === 'consumed-stale') {
                @trigger_error('older unrelated warning', E_USER_WARNING);
            }
            if ($environment !== 'ordinary') {
                set_error_handler(static fn (): bool => true);
            }
            try {
                $filter = $flag === 'include' ? new FileFilter([], [$pattern]) : new FileFilter([], [], [$pattern]);
                $actual = $filter->error();
            } finally {
                if ($environment !== 'ordinary') {
                    restore_error_handler();
                }
            }
            $prefix = 'Invalid --' . $flag . '-pattern: ';
            $valid = is_string($actual) && str_starts_with($actual, $prefix)
                && str_contains($actual, $reason) && !str_contains($actual, 'older unrelated warning');
            if (!$valid) {
                fwrite(STDERR, "FAIL environment={$environment} flag={$flag} pattern={$pattern}\n"
                    . "expected: a diagnostic containing '{$reason}', not an earlier warning\n"
                    . 'actual: ' . var_export($actual, true) . "\n");
                $failures++;
            }
            $count++;
        }
    }
}
if ($failures !== 0) {
    fwrite(STDERR, "FAIL: {$failures} of {$count} invalid-pattern diagnostics\n");
    exit(1);
}
printf("PASS: %d invalid-pattern diagnostics\n", $count);
