<?php

declare(strict_types=1);

use JonBaldie\ExplicitnessChecker\SourceChecker;

require __DIR__ . '/bootstrap.php';

$checker = new SourceChecker();
$count = 0;
foreach (programs(['bind-a', 'bind-b', 'unset', 'write'], 4) as $actions) {
    $target = null;
    $expected = [];
    $body = '';
    foreach ($actions as $action) {
        if ($action === 'bind-a' || $action === 'bind-b') {
            $target = substr($action, -1);
            $body .= '$r = &$GLOBALS["' . $target . '"];';
        } elseif ($action === 'unset') {
            $body .= 'unset($r);';
            $target = null;
        } else {
            $body .= '$r = 1;';
            if ($target !== null) {
                $finding = "wrote to \$GLOBALS['" . $target . "']";
                if (!in_array($finding, $expected, true)) {
                    $expected[] = $finding;
                }
            }
        }
    }
    expectSame(implode(',', $actions), $expected, outputDescriptions($checker, $body));
    $count++;
}
printf("PASS: %d generated global-reference programs\n", $count);
