<?php

declare(strict_types=1);

use JonBaldie\ExplicitnessChecker\Walk\ReflectedByReferenceParameters;

require dirname(__DIR__) . '/vendor/autoload.php';

$count = isset($argv[1]) ? (int) $argv[1] : 16000;
if ($count < 1) {
    fwrite(STDERR, "Expected a positive number of names.\n");
    exit(1);
}

$cache = new ReflectedByReferenceParameters();
$start = hrtime(true);
for ($i = 0; $i < $count; $i++) {
    $cache = $cache->withFunction('project_function_' . $i);
}
$cache = $cache->withFunction('sort');
$elapsed = (hrtime(true) - $start) / 1e9;
if ($cache->isBuiltin('project_function_' . ($count - 1)) || !$cache->isPassedByReference('SORT', 0, null)) {
    fwrite(STDERR, "Unexpected cache result.\n");
    exit(1);
}
printf("%d names %.6f seconds peak %.1f MiB\n", $count, $elapsed, memory_get_peak_usage(true) / 1048576);
