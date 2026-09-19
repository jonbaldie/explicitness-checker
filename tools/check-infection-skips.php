<?php

declare(strict_types=1);

/*
 * Infection skips, without failing, any mutant whose covering tests took longer
 * than the configured timeout on the initial run, and leaves it out of the MSI.
 * This fails the run if any mutant was skipped, so the MSI gates cover every
 * mutant.
 *
 * Usage: php tools/check-infection-skips.php [path to Infection's JSON log]
 */

$log = $argv[1] ?? dirname(__DIR__) . '/build/infection.json';
$data = is_file($log) ? json_decode((string) file_get_contents($log), true) : null;
$skipped = is_array($data) && is_array($data['stats'] ?? null) ? ($data['stats']['skippedCount'] ?? null) : null;

if (!is_int($skipped)) {
    echo "No skippedCount in {$log}; run Infection with the JSON log enabled.", PHP_EOL;
    exit(1);
}
if ($skipped > 0) {
    echo "Infection skipped {$skipped} mutants that took longer than the timeout in infection.json5.", PHP_EOL;
    exit(1);
}
