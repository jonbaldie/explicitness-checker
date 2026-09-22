<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Analyser;
use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\Finding;
use JonBaldie\ExplicitnessChecker\Mode;
use JonBaldie\ExplicitnessChecker\Scope\FunctionLikeFinder;
use JonBaldie\ExplicitnessChecker\SourceChecker;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
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

    public function testClassifiesExitAndDieByArgument(): void
    {
        $source = <<<'PHP'
            <?php
            function exit_status(): void { exit(1); }
            function exit_without_status(): void { exit; }
            function exit_message(): void { exit('bye'); }
            function exit_dynamic($value): void { exit($value); }
            function die_status(): void { die(1); }
            function die_without_status(): void { die; }
            function die_message(): void { die('bye'); }
            function die_dynamic($value): void { die($value); }
            function qualified_exit_status(): void { \exit(1); }
            function qualified_exit_message(): void { \exit('bye'); }
            function qualified_exit_dynamic($value): void { \exit($value); }
            function qualified_die_status(): void { \die(1); }
            function qualified_die_message(): void { \die('bye'); }
            function qualified_die_dynamic($value): void { \die($value); }
            PHP;

        $results = (new SourceChecker())->check($source, new Mode(true, false));

        self::assertSame(
            [
                ['exit_status', 'terminates the program (exit)', Category::STANDARD_OUTPUT],
                ['exit_without_status', 'terminates the program (exit)', Category::STANDARD_OUTPUT],
                ['exit_message', 'writes to standard output (exit)', Category::STANDARD_OUTPUT],
                ['exit_dynamic', 'terminates the program (exit)', Category::STANDARD_OUTPUT],
                ['die_status', 'terminates the program (die)', Category::STANDARD_OUTPUT],
                ['die_without_status', 'terminates the program (die)', Category::STANDARD_OUTPUT],
                ['die_message', 'writes to standard output (die)', Category::STANDARD_OUTPUT],
                ['die_dynamic', 'terminates the program (die)', Category::STANDARD_OUTPUT],
                ['qualified_exit_status', 'terminates the program (exit)', Category::STANDARD_OUTPUT],
                ['qualified_exit_message', 'writes to standard output (exit)', Category::STANDARD_OUTPUT],
                ['qualified_exit_dynamic', 'terminates the program (exit)', Category::STANDARD_OUTPUT],
                ['qualified_die_status', 'terminates the program (die)', Category::STANDARD_OUTPUT],
                ['qualified_die_message', 'writes to standard output (die)', Category::STANDARD_OUTPUT],
                ['qualified_die_dynamic', 'terminates the program (die)', Category::STANDARD_OUTPUT],
            ],
            array_map(
                static fn ($result): array => [
                    $result->getName(),
                    $result->getOutputs()[0]->getDescription(),
                    $result->getOutputs()[0]->getCategory(),
                ],
                $results,
            ),
        );
    }

    public function testThrowsOnSourceThatDoesNotParse(): void
    {
        $this->expectException(Error::class);
        (new SourceChecker())->check('<?php function {', new Mode(false, false));
    }

    /**
     * FunctionLikeFinder's and Analyser's resolved-AST contract, pinned from
     * both sides: without NameResolver, the `use function` import is mistaken
     * for the built-in and the static property keeps its source spelling; a
     * caller that resolves names first, as the PHPStan rule's parser does,
     * gets the same results SourceChecker reports.
     */
    public function testResolvedAstContractOfFinderAndAnalyser(): void
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $mode = new Mode(true, true);
        $analyze = static function (array $ast) use ($mode): array {
            $results = [];
            foreach ((new FunctionLikeFinder())->find($ast) as $functionLike) {
                $analysis = (new Analyser())->analyse($functionLike->getNode(), $mode);
                $results[] = [
                    $functionLike->getName(),
                    array_map(
                        static fn (Finding $finding): string => $finding->getDescription(),
                        array_merge($analysis->getImplicitInputs(), $analysis->getImplicitOutputs()),
                    ),
                ];
            }

            return $results;
        };

        $raw = (array) $parser->parse(self::SOURCE);
        self::assertSame(
            [
                ['App\demo', ['reads system time (time)']],
                ['App\K::m', ['wrote to static property Other\Thing::$shared']],
            ],
            $analyze($raw),
        );

        $resolved = (new NodeTraverser(new NameResolver()))->traverse($raw);
        self::assertSame(
            [
                ['App\demo', []],
                ['App\K::m', ['wrote to static property App\Other\Thing::$shared']],
            ],
            $analyze($resolved),
        );
        self::assertSame(
            [
                ['App\demo', []],
                ['App\K::m', ['wrote to static property App\Other\Thing::$shared']],
            ],
            array_map(
                static fn ($result): array => [
                    $result->getName(),
                    array_map(
                        static fn (Finding $finding): string => $finding->getDescription(),
                        array_merge($result->getInputs(), $result->getOutputs()),
                    ),
                ],
                (new SourceChecker())->check(self::SOURCE, $mode),
            ),
        );
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
