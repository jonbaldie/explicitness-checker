<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use JonBaldie\ExplicitnessChecker\Finding;
use JonBaldie\ExplicitnessChecker\FunctionAnalysis;
use JonBaldie\ExplicitnessChecker\FunctionResult;
use JonBaldie\ExplicitnessChecker\Cli\DiscoveredFiles;
use JonBaldie\ExplicitnessChecker\Cli\FileCheckResult;
use JonBaldie\ExplicitnessChecker\Cli\RunSummary;

foreach ([2000, 4000, 8000, 16000] as $count) {
    $finding = new Finding('read', 'x', 1);
    $function = new FunctionResult('f', 1, new FunctionAnalysis([$finding], [], []));
    $files = [];
    for ($i = 0; $i < $count; ++$i) {
        $files[] = new FileCheckResult("f$i.php", [$function]);
    }

    $start = hrtime(true);
    $summary = RunSummary::of(new DiscoveredFiles([]), $files, null);
    $elapsed = (hrtime(true) - $start) / 1e9;
    $violations = $summary->getViolations();
    if (count($violations) !== $count || $violations[0]->getFile() !== 'f0.php' || $violations[$count - 1]->getFile() !== 'f' . ($count - 1) . '.php') {
        throw new RuntimeException('Summary has incorrect rows or order');
    }
    printf("%d files, %d rows, %.6f s\n", $count, count($violations), $elapsed);
}
