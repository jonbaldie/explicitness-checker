<?php

declare(strict_types=1);

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\Cli\DiscoveredFiles;
use JonBaldie\ExplicitnessChecker\Cli\ExplicitnessMinimum;
use JonBaldie\ExplicitnessChecker\Cli\FileCheckResult;
use JonBaldie\ExplicitnessChecker\Cli\RunSummary;
use JonBaldie\ExplicitnessChecker\Cli\UncheckedInput;
use JonBaldie\ExplicitnessChecker\Finding;
use JonBaldie\ExplicitnessChecker\FunctionAnalysis;
use JonBaldie\ExplicitnessChecker\FunctionResult;

require __DIR__ . '/bootstrap.php';

$categories = [0 => null, 1 => Category::STANDARD_OUTPUT, 2 => Category::GLOBAL_VARIABLE, 3 => Category::FILE];
$count = 0;
foreach (programs(['0', '1', '2', '3'], 3) as $levels) {
    foreach ([null, '0', '50', '100'] as $minimum) {
        foreach ([false, true] as $parseFailure) {
            foreach ([false, true] as $unreadable) {
                $functions = [];
                $violations = 0;
                $worst = 0;
                foreach ($levels as $position => $level) {
                    $severity = (int) $level;
                    $findings = $severity === 0 ? [] : [new Finding('finding ' . $position, $categories[$severity], $position + 1)];
                    $functions[] = new FunctionResult('f' . $position, $position + 1, new FunctionAnalysis($findings, [], []));
                    if ($severity !== 0) {
                        $violations++;
                        $worst = max($worst, $severity);
                    }
                }
                $fileResults = [new FileCheckResult('generated.php', $functions)];
                if ($parseFailure) {
                    $fileResults[] = new FileCheckResult('broken.php', [], new UncheckedInput('broken.php', UncheckedInput::UNPARSEABLE_FILE));
                }
                if ($unreadable) {
                    $fileResults[] = new FileCheckResult('unreadable.php', [], new UncheckedInput('unreadable.php', UncheckedInput::UNREADABLE_FILE));
                }
                $threshold = $minimum === null ? null : new ExplicitnessMinimum($minimum);
                $summary = RunSummary::of(new DiscoveredFiles([]), $fileResults, $threshold);
                $checked = count($levels);
                $explicit = $checked - $violations;
                $met = $minimum === null || $checked === 0 || 100 * $explicit >= (int) $minimum * $checked;
                $exit = $minimum !== null && $met ? 0 : $worst;
                if ($parseFailure) {
                    $exit = max($exit, 2);
                }
                $case = 'levels=' . implode(',', $levels) . ' minimum=' . ($minimum ?? 'none')
                    . ' parse=' . (int) $parseFailure . ' unreadable=' . (int) $unreadable;
                expectSame($case . ' checked', $checked, $summary->getCheckedCount());
                expectSame($case . ' explicit', $explicit, $summary->getExplicitCount());
                expectSame($case . ' percentage', $checked === 0 ? 1000 : intdiv(1000 * $explicit, $checked), $summary->getExplicitnessTenths());
                expectSame($case . ' minimum', $met, $summary->isMinimumMet());
                expectSame($case . ' exit', $exit, $summary->getExitCode());
                $count++;
            }
        }
    }
}
printf("PASS: %d generated run-summary combinations\n", $count);
