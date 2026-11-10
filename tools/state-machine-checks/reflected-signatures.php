<?php

declare(strict_types=1);

use JonBaldie\ExplicitnessChecker\Cli\FileChecker;
use JonBaldie\ExplicitnessChecker\Mode;
use JonBaldie\ExplicitnessChecker\SourceChecker;
use JonBaldie\ExplicitnessChecker\Tests\Support\CountingByReferenceParameters;

require __DIR__ . '/bootstrap.php';

$count = 0;
foreach (programs(['sort', 'SORT', 'preg_match', 'unknown_signature_probe'], 3) as $actions) {
    $parameters = new CountingByReferenceParameters();
    $firstSpellings = [];
    foreach ($actions as $name) {
        $previous = $parameters;
        $previousCounts = $previous->reflections;
        $parameters = $parameters->withFunction($name);
        $firstSpellings[strtolower($name)] ??= $name;
        $expected = [];
        foreach ($firstSpellings as $first) {
            $expected[$first] = 1;
        }
        expectSame('previous snapshot ' . implode(',', $actions), $previousCounts, $previous->reflections);
        expectSame('one reflection per name ' . implode(',', $actions), $expected, $parameters->reflections);
    }
    foreach ($firstSpellings as $name => $first) {
        expectSame('builtin ' . $name, $name !== 'unknown_signature_probe', $parameters->isBuiltin(strtoupper($name)));
        expectSame('first parameter ' . $name, $name === 'sort', $parameters->isPassedByReference($first, 0, null));
    }
    $count++;
}
$firstPath = tempnam(sys_get_temp_dir(), 'ec-signature-first-');
$secondPath = tempnam(sys_get_temp_dir(), 'ec-signature-second-');
if ($firstPath === false || $secondPath === false) {
    throw new RuntimeException('Could not create temporary source files');
}
try {
    file_put_contents($firstPath, '<?php function first($x) { sort($x); }');
    file_put_contents($secondPath, '<?php function second($x) { SORT($x); }');
    $initial = new CountingByReferenceParameters();
    $checker = new FileChecker(new SourceChecker($initial), new Mode(false, false));
    $firstResult = $checker->check($firstPath);
    $secondResult = $checker->after($firstResult)->check($secondPath);
    $last = $secondResult->getNativeParameters();
    if (!$last instanceof CountingByReferenceParameters) {
        throw new RuntimeException('Expected a signature snapshot in the successful file result');
    }
    expectSame('cross-file signature handoff', ['sort' => 1], $last->reflections);
    expectSame('initial signature snapshot unchanged', [], $initial->reflections);
} finally {
    unlink($firstPath);
    unlink($secondPath);
}
printf("PASS: %d generated signature histories and one cross-file handoff\n", $count);
