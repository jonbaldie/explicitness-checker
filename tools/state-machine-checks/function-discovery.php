<?php

declare(strict_types=1);

use JonBaldie\ExplicitnessChecker\FunctionResult;
use JonBaldie\ExplicitnessChecker\Mode;
use JonBaldie\ExplicitnessChecker\SourceChecker;

require __DIR__ . '/bootstrap.php';

$checker = new SourceChecker();
$count = 0;
foreach (['', 'App', 'App\\Nested'] as $namespace) {
    foreach ([['First', 'Second'], ['Second', 'First']] as [$outer, $following]) {
        foreach (['inner', 'deeper'] as $method) {
            $prefix = $namespace === '' ? '' : $namespace . '\\';
            $declaration = $namespace === '' ? '' : 'namespace ' . $namespace . ';';
            $source = '<?php ' . $declaration
                . ' function top() {} class ' . $outer . ' {'
                . ' function first() { return new class { function ' . $method . '() {} }; }'
                . ' function later() {} } class ' . $following . ' { function second() {} }';
            $expected = [
                $prefix . 'top',
                $prefix . $outer . '::first',
                $prefix . $outer . '::class@anonymous::' . $method,
                $prefix . $outer . '::later',
                $prefix . $following . '::second',
            ];
            $actual = array_map(static fn (FunctionResult $result): string => $result->getName(), $checker->check($source, new Mode(false, false)));
            expectSame($source, $expected, $actual);
            $count++;
        }
    }
}
printf("PASS: %d generated function-discovery programs\n", $count);
