<?php

declare(strict_types=1);

use JonBaldie\ExplicitnessChecker\Cli\FileCheckResult;
use JonBaldie\ExplicitnessChecker\Mode;
use JonBaldie\ExplicitnessChecker\Project\ProjectAnalysis;
use JonBaldie\ExplicitnessChecker\SourceChecker;

require __DIR__ . '/bootstrap.php';

function verifyGraph(int $size, int $edges, int $seedBits): void
{
    $source = '<?php ';
    $seeds = [];
    for ($caller = 0; $caller < $size; $caller++) {
        $seeds[$caller] = ($seedBits >> ($caller * 2)) & 3;
        $source .= 'function n' . $caller . '($arg) {';
        if ($seeds[$caller] !== 0) {
            $source .= 'global $seed' . $caller . ';';
            if (($seeds[$caller] & 1) !== 0) {
                $source .= '$read = $seed' . $caller . ';';
            }
            if (($seeds[$caller] & 2) !== 0) {
                $source .= '$seed' . $caller . ' = 1;';
            }
        }
        for ($callee = 0; $callee < $size; $callee++) {
            if (($edges & (1 << ($caller * $size + $callee))) !== 0) {
                $source .= 'n' . $callee . '(0);';
            }
        }
        $source .= '}';
    }
    $checker = new SourceChecker();
    $ast = $checker->parse($source);
    $file = new FileCheckResult('generated.php', $checker->checkAst($ast, new Mode(false, false)), null, $ast);
    $result = (new ProjectAnalysis())->propagate([$file])[0]->getFunctions();
    foreach ($result as $start => $function) {
        $seen = [];
        $pending = [$start];
        $expected = 0;
        while ($pending !== []) {
            $current = array_pop($pending);
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            $expected |= $seeds[$current];
            for ($callee = 0; $callee < $size; $callee++) {
                if (($edges & (1 << ($current * $size + $callee))) !== 0) {
                    $pending[] = $callee;
                }
            }
        }
        $actual = ($function->getInputs() !== [] ? 1 : 0) | ($function->getOutputs() !== [] ? 2 : 0);
        expectSame("size={$size} edges={$edges} seeds={$seedBits} start={$start}", $expected, $actual);
    }
}

$count = 0;
for ($edges = 0; $edges < 16; $edges++) {
    for ($seeds = 0; $seeds < 16; $seeds++) {
        verifyGraph(2, $edges, $seeds);
        $count++;
    }
}
foreach ([34, 98, 136, 38] as $edges) {
    for ($seeds = 0; $seeds < 64; $seeds++) {
        verifyGraph(3, $edges, $seeds);
        $count++;
    }
}
for ($seeds = 0; $seeds < 256; $seeds++) {
    verifyGraph(4, 2114, $seeds);
    $count++;
}
printf("PASS: %d generated project graphs\n", $count);
