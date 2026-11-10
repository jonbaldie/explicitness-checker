<?php

declare(strict_types=1);

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\Finding;
use JonBaldie\ExplicitnessChecker\FindingCollector;
use JonBaldie\ExplicitnessChecker\SourceChecker;
use PhpParser\Node\Expr\Variable;

require __DIR__ . '/bootstrap.php';

/** @return list<array{string, int, bool}> */
function findingRows(array $findings): array
{
    return array_map(static fn (Finding $finding): array => [$finding->getDescription(), $finding->getLine(), $finding->isOutput()], $findings);
}

$count = 0;
foreach (programs(['read-a', 'read-b', 'write-a', 'write-b'], 4) as $actions) {
    $collector = new FindingCollector();
    $expected = [];
    foreach ($actions as $position => $action) {
        $old = $collector;
        $oldRows = findingRows($old->findings());
        $node = new Variable('x', ['startLine' => $position + 1]);
        $isOutput = str_starts_with($action, 'write');
        $collector = $isOutput
            ? $collector->output($action, Category::GLOBAL_VARIABLE, $node)
            : $collector->input($action, Category::GLOBAL_VARIABLE, $node);
        $expected[$action] ??= [$action, $position + 1, $isOutput];
        expectSame('immutable previous snapshot ' . implode(',', $actions), $oldRows, findingRows($old->findings()));
        expectSame('first occurrence ' . implode(',', $actions), array_values($expected), findingRows($collector->findings()));
    }
    $count++;
}
$source = "<?php\nfunction f() {\n global \$x;\n \$a = \$x;\n \$b = \$x;\n \$x = 1;\n \$x = 2;\n}";
$result = (new SourceChecker())->check($source, new JonBaldie\ExplicitnessChecker\Mode(false, false))[0];
expectSame('real source findings', [
    ['read from global variable $x', 4, false],
    ['wrote to global variable $x', 6, true],
], findingRows($result->getFindings()));
printf("PASS: %d generated finding sequences and one source program\n", $count);
