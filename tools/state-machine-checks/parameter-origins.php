<?php

declare(strict_types=1);

use JonBaldie\ExplicitnessChecker\SourceChecker;

require __DIR__ . '/bootstrap.php';

$checker = new SourceChecker();
$count = 0;
foreach (programs(['share', 'fresh', 'clone', 'unset'], 4) as $actions) {
    $shared = false;
    $body = '';
    foreach ($actions as $action) {
        if ($action === 'share') {
            $body .= '$local = $param;';
            $shared = true;
        } elseif ($action === 'fresh') {
            $body .= '$local = new stdClass();';
            $shared = false;
        } elseif ($action === 'clone') {
            $body .= '$local = clone $param;';
            $shared = false;
        } else {
            $body .= 'unset($local);';
            $shared = false;
        }
    }
    $body .= '$local->value = 1;';
    expectSame(implode(',', $actions), $shared ? ['wrote to argument $param'] : [], outputDescriptions($checker, $body));
    $count++;
}
printf("PASS: %d generated parameter-origin programs\n", $count);
