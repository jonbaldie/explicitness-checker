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

    /**
     * A static call with no arguments can only get its data from outside the
     * function's arguments. Calls that pass arguments, calls on the current
     * class (self::, parent::, static::), calls whose class or method is
     * named by an argument, and first-class callables (`Str::make(...)`) are
     * not reported.
     */
    public function testReportsArgumentlessStaticCallsAsImplicitInputsInDefaultMode(): void
    {
        $source = <<<'PHP'
            <?php
            namespace App;
            function accesses_static_helper() {
                $data = SomeClass::staticMethod();
                return \Other\Clock::NOW() . Str::slug($data) . Str::make(...);
            }
            class Child extends Base {
                public function m() { return parent::m() . self::a() . static::b(); }
            }
            function dynamic(string $class, string $method) { return $class::make() . SomeClass::$method(); }
            PHP;
        $results = (new SourceChecker())->check($source, new Mode(false, false));

        self::assertSame(
            [
                ['App\accesses_static_helper', 3, [
                    'read from static method App\SomeClass::staticMethod()',
                    'read from static method Other\Clock::NOW()',
                ], []],
                ['App\Child::m', 8, [], []],
                ['App\dynamic', 10, [], []],
            ],
            $this->summaries($results),
        );
        self::assertSame(Category::STATIC_CALL, $results[0]->getInputs()[0]->getCategory());
        self::assertSame(4, $results[0]->getInputs()[0]->getLine());
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
     * #46: PHP function names are case-insensitive, so strict mode detects
     * catalogue calls written in any casing, with the source spelling kept in
     * the description. fopen's mode classification follows the same spelling.
     */
    public function testDetectsCatalogueCallsWhateverTheirCasing(): void
    {
        $source = <<<'PHP'
            <?php
            function writes_uppercase(): void { VAR_DUMP([1, 2]); }
            function reads_time_mixed_case(): void { $now = Time(); echo $now; }
            function reads_random_mixed_case(): void { $number = Rand(1, 10); echo $number; }
            function reads_file_mixed_case(): void { $stream = FOPEN('php://memory', 'r'); var_dump($stream); }
            PHP;
        $results = (new SourceChecker())->check($source, new Mode(true, false));

        self::assertSame(
            [
                ['writes_uppercase', 2, [], ['writes to standard output (VAR_DUMP)']],
                ['reads_time_mixed_case', 3, ['reads system time (Time)'], ['writes to standard output (echo)']],
                ['reads_random_mixed_case', 4, ['reads from random number generator (Rand)'], ['writes to standard output (echo)']],
                ['reads_file_mixed_case', 5, ['reads from file (FOPEN)'], ['writes to standard output (var_dump)']],
            ],
            $this->summaries($results),
        );
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
     * #72: a dynamic property name is read, not written, even when the
     * property it names is the target of a write.
     */
    public function testDynamicPropertyNamesAreRead(): void
    {
        $source = <<<'PHP'
            <?php
            function write_dynamic_property($o): void { global $name; $o->{$name} = 1; }
            function write_dynamic_static_property(): void { global $n; Foo::${$n} = 1; }
            function unset_dynamic_property($o): void { global $name; unset($o->{$name}); }
            function read_dynamic_property($o) { global $name; return $o->{$name}; }
            PHP;
        $results = (new SourceChecker())->check($source, new Mode(false, false));

        self::assertSame(
            [
                ['write_dynamic_property', 2, ['read from global variable $name'], ['wrote to argument $o']],
                ['write_dynamic_static_property', 3, ['read from global variable $n'], []],
                ['unset_dynamic_property', 4, ['read from global variable $name'], ['wrote to argument $o']],
                ['read_dynamic_property', 5, ['read from global variable $name'], []],
            ],
            $this->summaries($results),
        );
    }

    /**
     * #72: a write through a by-reference parameter changes the caller's
     * data. Reading it, and writing a by-value copy, stay explicit.
     */
    public function testReportsWritesThroughByReferenceParametersAsArgumentMutation(): void
    {
        $source = <<<'PHP'
            <?php
            function add_item(array &$cart, string $name): void { $cart[] = $name; }
            function empty_cart(array &$cart): void { $cart = []; }
            function count_cart(array &$cart): int { return count($cart); }
            function set_first(array $items): array { $items[0] = 1; return $items; }
            $append = function (array &$list) { $list[] = 1; };
            $clear = fn (array &$list) => $list = [];
            PHP;
        $results = (new SourceChecker())->check($source, new Mode(false, false));

        self::assertSame(
            [
                ['add_item', 2, [], ['wrote to argument $cart']],
                ['empty_cart', 3, [], ['wrote to argument $cart']],
                ['count_cart', 4, [], []],
                ['set_first', 5, [], []],
                ['{closure}', 6, [], ['wrote to argument $list']],
                ['{closure}', 7, [], ['wrote to argument $list']],
            ],
            $this->summaries($results),
        );
        self::assertSame(Category::ARGUMENT_MUTATION, $results[0]->getOutputs()[0]->getCategory());
        self::assertSame(2, $results[0]->getOutputs()[0]->getLine());
    }

    /**
     * #72: an object argument is a handle the caller shares, so writing or
     * unsetting a property reached from it changes the caller's data, however
     * deep the property. Reading it stays explicit.
     */
    public function testReportsPropertyWritesThroughObjectArgumentsAsArgumentMutation(): void
    {
        $source = <<<'PHP'
            <?php
            function set_price($item, $price): void { $item->price = $price; }
            function drop_name($user): void { unset($user->first_name); }
            function rename_customer($order): void { $order->customer->name = 'x'; $order->customer->email = 'y'; }
            function add_to($cart): void { $cart->items[] = 1; }
            function reprice(array $items): void { $items[0]->price = 1; }
            function first_name($user): string { return $user->first_name; }
            function bump($counter): void { $counter->count++; $counter->total += 1; }
            function local(): void { $o = new stdClass(); $o->x = 1; }
            PHP;
        $results = (new SourceChecker())->check($source, new Mode(false, false));

        self::assertSame(
            [
                ['set_price', 2, [], ['wrote to argument $item']],
                ['drop_name', 3, [], ['wrote to argument $user']],
                ['rename_customer', 4, [], ['wrote to argument $order']],
                ['add_to', 5, [], ['wrote to argument $cart']],
                ['reprice', 6, [], ['wrote to argument $items']],
                ['first_name', 7, [], []],
                ['bump', 8, [], ['wrote to argument $counter']],
                ['local', 9, [], []],
            ],
            $this->summaries($results),
        );
    }

    /**
     * #72: a static variable survives between calls, so reading or writing it
     * makes the result depend on earlier calls. The declaration itself is
     * neither, but its initial value is read.
     */
    public function testReportsStaticVariablesAsReadAndWritten(): void
    {
        $source = <<<'PHP'
            <?php
            function next_id(): int { static $id = 0; return ++$id; }
            function declares_only(): void { static $x = 0; }
            function reads_only(): int { static $x = 0; return $x; }
            function pair(): void { static $a, $b = 1; $a = $b; }
            function cache($key) { static $cache; return $cache[$key] ??= $key; }
            function initialised(): void { global $seed; static $x = $seed; }
            function outer(): void { $f = function () { static $n = 0; $n++; }; $n = 1; }
            PHP;
        $results = (new SourceChecker())->check($source, new Mode(false, false));

        self::assertSame(
            [
                ['next_id', 2, ['read from static variable $id'], ['wrote to static variable $id']],
                ['declares_only', 3, [], []],
                ['reads_only', 4, ['read from static variable $x'], []],
                ['pair', 5, ['read from static variable $b'], ['wrote to static variable $a']],
                ['cache', 6, ['read from static variable $cache'], ['wrote to static variable $cache']],
                ['initialised', 7, ['read from global variable $seed'], []],
                ['outer', 8, [], []],
                ['{closure}', 8, ['read from static variable $n'], ['wrote to static variable $n']],
            ],
            $this->summaries($results),
        );
        self::assertSame(Category::STATIC_VARIABLE, $results[0]->getInputs()[0]->getCategory());
        self::assertSame(Category::STATIC_VARIABLE, $results[0]->getOutputs()[0]->getCategory());
    }

    /**
     * #72: a closure that captures by reference shares the variable with its
     * enclosing scope. A by-value capture, including an arrow function's, is a
     * snapshot taken when the closure is created, so it stays explicit.
     */
    public function testReportsByReferenceClosureCapturesAsReadAndWritten(): void
    {
        $source = <<<'PHP'
            <?php
            $counter = function () use (&$n) { return ++$n; };
            $reader = function () use (&$n) { return $n; };
            $snapshot = function () use ($n) { return $n + 1; };
            $arrow = fn () => $n + 1;
            PHP;
        $results = (new SourceChecker())->check($source, new Mode(false, false));

        self::assertSame(
            [
                ['{closure}', 2, ['read from captured reference $n'], ['wrote to captured reference $n']],
                ['{closure}', 3, ['read from captured reference $n'], []],
                ['{closure}', 4, [], []],
                ['{closure}', 5, [], []],
            ],
            $this->summaries($results),
        );
        self::assertSame(Category::CAPTURED_REFERENCE, $results[0]->getInputs()[0]->getCategory());
        self::assertSame(Category::CAPTURED_REFERENCE, $results[0]->getOutputs()[0]->getCategory());
    }

    /**
     * #72: a static property is process-wide mutable state, like a global, so
     * default mode reports it. `--props` still adds `$this->x` access.
     */
    public function testReportsStaticPropertiesInDefaultModeAndThisPropertiesUnderProps(): void
    {
        $source = <<<'PHP'
            <?php
            class Counter {
                public static int $n = 0;
                public int $x = 0;
                public function bump(): int { $this->x = 1; return ++Counter::$n; }
            }
            PHP;

        self::assertSame(
            [['Counter::bump', 5, ['read from static property Counter::$n'], ['wrote to static property Counter::$n']]],
            $this->summaries((new SourceChecker())->check($source, new Mode(false, false))),
        );
        self::assertSame(
            [['Counter::bump', 5, ['read from static property Counter::$n'], [
                'wrote to object property $this->x',
                'wrote to static property Counter::$n',
            ]]],
            $this->summaries((new SourceChecker())->check($source, new Mode(false, true))),
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
