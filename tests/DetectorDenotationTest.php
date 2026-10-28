<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Mode;
use JonBaldie\ExplicitnessChecker\SourceChecker;
use PHPUnit\Framework\TestCase;

class DetectorDenotationTest extends TestCase
{
    public function testChangingDenotationsAreUsedThroughoutOneFunction(): void
    {
        $source = <<<'PHP'
            <?php
            function example($first, $second) {
                $local = $first;
                $local->value = 1;
                $local = $second;
                $local->value = 2;
                $alias = &$GLOBALS['one'];
                $alias = 1;
                $alias = &$GLOBALS['two'];
                $alias = 2;
                unset($alias);
                $alias = 3;
            }
            PHP;
        $result = (new SourceChecker())->check($source, new Mode(false, false))[0];
        self::assertSame([], $result->getInputs());
        self::assertSame(
            ['wrote to argument $first', 'wrote to argument $second', "wrote to \$GLOBALS['one']", "wrote to \$GLOBALS['two']"],
            array_map(static fn ($finding) => $finding->getDescription(), $result->getOutputs()),
        );
    }

    public function testDenotationsAreIsolatedBetweenFunctions(): void
    {
        $source = <<<'PHP'
            <?php
            function first($param) {
                $local = $param;
                $local->value = 1;
                $alias = &$GLOBALS['one'];
                $alias = 1;
            }
            function second($param) {
                $local = new stdClass();
                $local->value = 1;
                $alias = 1;
            }
            function third($param) {
                $local = $param;
                $local->value = 1;
                $alias = &$GLOBALS['two'];
                $alias = 1;
            }
            PHP;
        $results = (new SourceChecker())->check($source, new Mode(false, false));
        self::assertCount(3, $results);
        self::assertSame(
            [
                ['wrote to argument $param', "wrote to \$GLOBALS['one']"],
                [],
                ['wrote to argument $param', "wrote to \$GLOBALS['two']"],
            ],
            array_map(static fn ($result) => array_map(static fn ($finding) => $finding->getDescription(), $result->getFindings()), $results),
        );
    }
}
