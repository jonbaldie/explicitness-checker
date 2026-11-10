<?php

declare(strict_types=1);

use JonBaldie\ExplicitnessChecker\SourceChecker;

require __DIR__ . '/bootstrap.php';

$checker = new SourceChecker();
$operations = [
    'keep' => '',
    'a' => '$r = &$GLOBALS["a"];',
    'b' => '$r = &$GLOBALS["b"];',
    'unset' => 'unset($r);',
];
$count = 0;
foreach (['keep', 'a', 'b'] as $initial) {
    foreach ($operations as $left => $leftCode) {
        foreach ($operations as $right => $rightCode) {
            $expected = [];
            foreach ([$initial, $left === 'keep' ? $initial : $left, $right === 'keep' ? $initial : $right] as $target) {
                if ($target === 'a' || $target === 'b') {
                    $expected[] = "wrote to \$GLOBALS['" . $target . "']";
                }
            }
            $expected = array_values(array_unique($expected));
            sort($expected);
            $body = $operations[$initial] . 'if ($flag) {' . $leftCode . '} else {' . $rightCode . '} $r = 1;';
            $actual = outputDescriptions($checker, $body);
            sort($actual);
            expectSame("initial={$initial} left={$left} right={$right}", $expected, $actual);
            $count++;
        }
    }
}
foreach (['a', 'b'] as $target) {
    $body = $operations[$target] . 'foreach ([] as &$r) {} $r = 1;';
    expectSame('zero-iteration ' . $target, ["wrote to \$GLOBALS['" . $target . "']"], outputDescriptions($checker, $body));
    $count++;
}
printf("PASS: %d generated branch and loop programs\n", $count);
