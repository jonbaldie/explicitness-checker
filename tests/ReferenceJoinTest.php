<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Mode;
use JonBaldie\ExplicitnessChecker\SourceChecker;
use PHPUnit\Framework\TestCase;

class ReferenceJoinTest extends TestCase
{
    /** @dataProvider branches */
    public function testReferenceAlternativesSurviveBranches(string $body, array $expected): void
    {
        $result = (new SourceChecker())->check('<?php function f(&$p, $flag, $items) {' . $body . '}', new Mode(false, false))[0];
        self::assertSame($expected, array_map(static fn ($finding) => $finding->getDescription(), $result->getOutputs()));
    }

    public function testReadWriteAlternativesKeepTheirLocationAndDirection(): void
    {
        $source = <<<'PHP'
            <?php
            function f($flag) {
                if ($flag) { $r = &$GLOBALS['first']; }
                else { $r = &$GLOBALS['second']; }
                $r++;
            }
            PHP;
        $result = (new SourceChecker())->check($source, new Mode(false, false))[0];
        self::assertSame([
            ["read from \$GLOBALS['first']", 5, false],
            ["read from \$GLOBALS['second']", 5, false],
            ["wrote to \$GLOBALS['first']", 5, true],
            ["wrote to \$GLOBALS['second']", 5, true],
        ], array_map(static fn ($finding) => [$finding->getDescription(), $finding->getLine(), $finding->isOutput()], $result->getFindings()));
    }

    public function testNestedScopesDoNotShareReferenceSnapshots(): void
    {
        $source = <<<'PHP'
            <?php
            function outer($flag) {
                $r = &$GLOBALS['first'];
                if ($flag) {
                    $closure = function () { $r = &$GLOBALS['second']; $r = 1; };
                    function inner() { $r = 2; }
                }
                $r = 3;
            }
            PHP;
        $results = (new SourceChecker())->check($source, new Mode(false, false));
        self::assertCount(3, $results);
        self::assertSame("wrote to \$GLOBALS['first']", $results[0]->getOutputs()[0]->getDescription());
        self::assertSame("wrote to \$GLOBALS['second']", $results[1]->getOutputs()[0]->getDescription());
        self::assertSame([], $results[2]->getFindings());
    }

    /** @return iterable<string, array{string, list<string>}> */
    public function branches(): iterable
    {
        yield 'two targets' => ['if ($flag) { $r = &$GLOBALS["first"]; } else { $r = &$GLOBALS["second"]; } $r = 7;', ["wrote to \$GLOBALS['first']", "wrote to \$GLOBALS['second']"]];
        yield 'sibling unset' => ['$r = &$GLOBALS["first"]; if ($flag) { unset($r); } else { $r = 7; }', ["wrote to \$GLOBALS['first']"]];
        yield 'zero foreach' => ['$r = &$GLOBALS["first"]; foreach ($items as &$r) {} $r = 7;', ["wrote to \$GLOBALS['first']"]];
        yield 'optional parameter alias' => ['if ($flag) { $p = &$GLOBALS["first"]; } $p = 1;', ["wrote to \$GLOBALS['first']", 'wrote to argument $p']];
        yield 'optional alias retains declared channel' => ['global $r; if ($flag) { $r = &$GLOBALS["first"]; } $r = 7;', ["wrote to \$GLOBALS['first']", 'wrote to global variable $r']];
        yield 'global declaration replaces alias' => ['$r = &$GLOBALS["first"]; global $r; $r = 7;', ['wrote to global variable $r']];
        yield 'read alternatives' => ['if ($flag) { $r = &$GLOBALS["first"]; } else { $r = &$GLOBALS["second"]; } $r++;', ["wrote to \$GLOBALS['first']", "wrote to \$GLOBALS['second']"]];
        yield 'same target once' => ['if ($flag) { $r = &$GLOBALS["first"]; } else { $r = &$GLOBALS["first"]; } $r = 1; $r = 2;', ["wrote to \$GLOBALS['first']"]];
        yield 'conditional unset after join' => ['$r = &$GLOBALS["first"]; if ($flag) { unset($r); } $r = 7;', ["wrote to \$GLOBALS['first']"]];
        yield 'nested isolation' => ['$r = &$GLOBALS["first"]; if ($flag) { if ($flag) { unset($r); } } else { $r = 7; } $r = 8;', ["wrote to \$GLOBALS['first']"]];
        yield 'ordinary alternative retains object origin' => ['$r = $items; if ($flag) { $r = &$GLOBALS["first"]; } $r->x = 1;', ['wrote to argument $items', "wrote to \$GLOBALS['first']"]];
        yield 'short circuit' => ['$flag && ($r = &$GLOBALS["first"]); $r = 7;', ["wrote to \$GLOBALS['first']"]];
        yield 'ternary arms' => ['$flag ? ($r = &$GLOBALS["first"]) : ($r = &$GLOBALS["second"]); $r = 7;', ["wrote to \$GLOBALS['first']", "wrote to \$GLOBALS['second']"]];
        yield 'while zero path' => ['$r = &$GLOBALS["first"]; while ($flag) { unset($r); } $r = 7;', ["wrote to \$GLOBALS['first']"]];
        yield 'for zero path' => ['$r = &$GLOBALS["first"]; for (; $flag;) { unset($r); } $r = 7;', ["wrote to \$GLOBALS['first']"]];
        yield 'switch cascade' => ['switch ($flag) { case 0: $r = &$GLOBALS["first"]; case 1: $r = 7; }', ["wrote to \$GLOBALS['first']"]];
        yield 'exception cascade' => ['try { $r = &$GLOBALS["first"]; } catch (Exception $error) { $r = 7; }', ["wrote to \$GLOBALS['first']"]];
        yield 'non globals reference only in sibling' => ['$r = &$GLOBALS["first"]; if ($flag) { $r = &$items; } else { $r = 7; }', ["wrote to \$GLOBALS['first']"]];
        yield 'unconditional non globals reference replaces alternatives' => ['if ($flag) { $r = &$GLOBALS["first"]; } $r = &$items; $r = 7;', []];
        yield 'unconditional unset replaces alternatives' => ['if ($flag) { $r = &$GLOBALS["first"]; } unset($r); $r = 7;', []];
        yield 'unconditional parameter alias' => ['$p = &$GLOBALS["first"]; $p = 1;', ["wrote to \$GLOBALS['first']"]];
    }
}
