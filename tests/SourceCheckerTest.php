<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\Finding;
use JonBaldie\ExplicitnessChecker\Mode;
use JonBaldie\ExplicitnessChecker\SourceChecker;
use PhpParser\Error;
use PHPUnit\Framework\TestCase;

/**
 * The source-level check owns parsing and name resolution (#35), so callers
 * get the same findings the CLI and the PHPStan rule report, with the same
 * PHP-semantics names, whether or not they know about php-parser's
 * NameResolver.
 */
class SourceCheckerTest extends TestCase
{
    protected const SOURCE = <<<'PHP'
        <?php
        namespace App;
        use function Other\time;
        function demo(): void { time(); }
        class K { public function m(): void { Other\Thing::$shared = 1; } }
        PHP;

    public function testResolvesNamesSoImportsAreNotMistakenForBuiltins(): void
    {
        $results = (new SourceChecker())->check(self::SOURCE, new Mode(true, true));

        self::assertSame(
            [
                ['App\demo', 4, [], []],
                ['App\K::m', 5, [], ['wrote to static property App\Other\Thing::$shared']],
            ],
            $this->summaries($results),
        );
    }

    public function testFindingsCarryDescriptionCategoryAndLine(): void
    {
        $results = (new SourceChecker())->check(self::SOURCE, new Mode(true, true));
        $outputs = $results[1]->getOutputs();

        self::assertCount(1, $outputs);
        self::assertInstanceOf(Finding::class, $outputs[0]);
        self::assertSame('wrote to static property App\Other\Thing::$shared', $outputs[0]->getDescription());
        self::assertSame(Category::STATIC_PROPERTY, $outputs[0]->getCategory());
        self::assertSame(5, $outputs[0]->getLine());
    }

    public function testReportsImplicitInputsOfEveryFunctionLike(): void
    {
        $source = <<<'PHP'
            <?php
            function reads(): void { echo $_GET['page']; }
            PHP;
        $results = (new SourceChecker())->check($source, new Mode(true, false));

        self::assertSame(
            [['reads', 2, ['read from superglobal $_GET'], ['writes to standard output (echo)']]],
            $this->summaries($results),
        );
    }

    public function testThrowsOnSourceThatDoesNotParse(): void
    {
        $this->expectException(Error::class);
        (new SourceChecker())->check('<?php function {', new Mode(false, false));
    }

    /**
     * @param list<\JonBaldie\ExplicitnessChecker\FunctionResult> $results
     *
     * @return list<array{string, int, list<string>, list<string>}>
     */
    protected function summaries(array $results): array
    {
        return array_map(
            static fn ($result): array => [
                $result->getName(),
                $result->getLine(),
                array_map(static fn (Finding $finding): string => $finding->getDescription(), $result->getInputs()),
                array_map(static fn (Finding $finding): string => $finding->getDescription(), $result->getOutputs()),
            ],
            $results,
        );
    }
}
