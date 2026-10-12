<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\Mode;
use JonBaldie\ExplicitnessChecker\SourceChecker;
use JonBaldie\ExplicitnessChecker\Tests\Support\Process;
use PHPUnit\Framework\TestCase;

class ArgumentOriginTest extends TestCase
{
    /** @dataProvider bindings */
    public function testLocalPropertyWritesNameTheCallerArgument(string $binding, string $write): void
    {
        $source = "<?php\nfunction f(\$param): void {\n" . $binding . "\n" . $write . "\n}";
        $result = (new SourceChecker())->check($source, new Mode(false, false))[0];
        self::assertSame([], $result->getInputs());
        self::assertCount(1, $result->getOutputs());
        $finding = $result->getOutputs()[0];
        self::assertSame('wrote to argument $param', $finding->getDescription());
        self::assertSame('param', $finding->getVariable());
        self::assertSame(Category::ARGUMENT_MUTATION, $finding->getCategory());
        self::assertTrue($finding->isOutput());
        self::assertSame(4, $finding->getLine());
    }

    /** @dataProvider cleanBindings */
    public function testBindingsDoNotInventCallerMutations(string $body): void
    {
        $result = (new SourceChecker())->check('<?php function f($param) {' . $body . '}', new Mode(false, false))[0];
        self::assertSame([], $result->getFindings());
    }

    /** @return iterable<string, array{string}> */
    public function cleanBindings(): iterable
    {
        yield 'unused binding' => ['$local = $param;'];
        yield 'read only' => ['$local = $param; return $local->x;'];
        yield 'copied array' => ['$local = $param; $local[] = 1; sort($local);'];
        yield 'fresh new' => ['$local = $param; $local = new stdClass(); $local->x = 1;'];
        yield 'fresh clone' => ['$local = $param; $local = clone $local; $local->x = 1;'];
        yield 'fresh nested new' => ['$local = $param; $local = new stdClass(); $local->parent->x = 1;'];
        yield 'unrelated binding' => ['$local = $param; $local = $other; $local->x = 1;'];
        yield 'unset local' => ['$local = $param; unset($local); $local->x = 1;'];
        yield 'foreach key' => ['foreach ($param as $local => $value) { $local->x = 1; }'];
        yield 'foreach replaces old origin in body' => ['$local = $param; foreach ([] as $local) { $local->x = 1; }'];
        yield 'catch replacement' => ['$local = $param; try {} catch (Exception $local) { $local->x = 1; }'];
        yield 'compound rebind' => ['$local = $param; $local += []; $local->x = 1;'];
        yield 'clone own array' => ['$local = clone $param; $local->items[] = 1; sort($local->items);'];
        yield 'clone chain' => ['$first = clone $param; $local = $first; $local->x = 1;'];
        yield 'fresh other branch' => ['$local = new stdClass(); if ($flag) { $local = $param; } else { $local->x = 1; }'];
        yield 'conditional clone top level' => ['$local = $flag ? clone $param : new stdClass(); $local->x = 1;'];
        yield 'unknown return' => ['$local = id($param); $local->x = 1;'];
    }

    /** @dataProvider joinedOrigins */
    public function testJoinsPreserveAllPossibleOrigins(string $body, array $roots): void
    {
        $result = (new SourceChecker())->check('<?php function f($param, $other, $flag) {' . $body . '}', new Mode(false, false))[0];
        self::assertSame([], $result->getInputs());
        self::assertSame($roots, array_map(static fn ($finding) => $finding->getVariable(), $result->getOutputs()));
    }

    /** @return iterable<string, array{string, list<string>}> */
    public function joinedOrigins(): iterable
    {
        yield 'conditional new' => ['$local = $param; if ($flag) { $local = new stdClass(); } $local->x = 1;', ['param']];
        yield 'conditional clone' => ['$local = $param; if ($flag) { $local = clone $local; } $local->x = 1;', ['param']];
        yield 'conditional unset' => ['$local = $param; if ($flag) { unset($local); } $local->x = 1;', ['param']];
        yield 'conditional value rebind' => ['$local = $param; $local = $flag ? new stdClass() : $local; $local->x = 1;', ['param']];
        yield 'conditional value roots' => ['$local = $flag ? $param : $other; $local->x = 1;', ['param', 'other']];
        yield 'coalesced roots' => ['$local = $param ?? $other; $local->x = 1;', ['param', 'other']];
        yield 'short ternary roots' => ['$local = $param ?: $other; $local->x = 1;', ['param', 'other']];
        yield 'two branches' => ['if ($flag) { $local = $param; } else { $local = $other; } $local->x = 1; $local->x = 2;', ['param', 'other']];
        yield 'else does not lose prior origin' => ['$local = $param; if ($flag) { $local = new stdClass(); } else { $local->x = 1; }', ['param']];
        yield 'zero foreach' => ['$local = $param; foreach ([] as $local) {} $local->x = 1;', ['param']];
        yield 'foreach two origins' => ['$local = $param; foreach ($other as $local) {} $local->x = 1;', ['param', 'other']];
        yield 'loop replacement' => ['$local = $param; while ($flag) { $local = new stdClass(); } $local->x = 1;', ['param']];
        yield 'short circuit' => ['$local = $param; $flag && ($local = new stdClass()); $local->x = 1;', ['param']];
        yield 'ternary arms' => ['$flag ? ($local = $param) : ($local = $other); $local->x = 1;', ['param', 'other']];
        yield 'switch fallthrough' => ['switch ($flag) { case 0: $local = $param; case 1: $local->x = 1; }', ['param']];
        yield 'try finally' => ['try { $local = $param; } finally { $local->x = 1; }', ['param']];
        yield 'catch possible prior assignment' => ['try { $local = $param; } catch (Exception $error) { $local->x = 1; }', ['param']];
        yield 'nested branches' => ['if ($flag) { if ($flag) { $local = $param; } else { $local = $other; } } $local->x = 1;', ['param', 'other']];
        yield 'alias snapshot' => ['$local = $param; $param = clone $param; $local->x = 1;', ['param']];
        yield 'local snapshot' => ['$first = $param; $local = $first; $first = new stdClass(); $local->x = 1;', ['param']];
        yield 'same branch clone suppresses only own writes' => ['$local = $param; if ($flag) { $local = clone $local; $local->x = 1; } $local->parent->x = 1;', ['param']];
    }

    public function testNestedScopesDoNotInheritOrEraseLocalOrigins(): void
    {
        $source = '<?php function outer($param) { $local = $param; $closure = function () use ($local) { $local->x = 1; }; function inner($param) { $local = new stdClass(); $local->x = 1; } $local->x = 1; }';
        $results = (new SourceChecker())->check($source, new Mode(false, false));
        self::assertCount(3, $results);
        self::assertSame('wrote to argument $param', $results[0]->getOutputs()[0]->getDescription());
        self::assertSame([], $results[1]->getFindings());
        self::assertSame([], $results[2]->getFindings());
    }

    public function testReferenceAndDeclarationChannelsKeepPrecedence(): void
    {
        $source = <<<'PHP'
            <?php
            function reference($param) {
                $local = $param;
                $local = &$GLOBALS['cart'];
                $local->x = 1;
                unset($local);
                $local->x = 2;
            }
            function declared($param) { global $local; $local = $param; $local->x = 1; }
            function stored($param) { static $local; $local = $param; $local->x = 1; }
            PHP;
        $results = (new SourceChecker())->check($source, new Mode(false, false));
        self::assertSame([Category::GLOBALS_ARRAY], array_map(static fn ($finding) => $finding->getCategory(), $results[0]->getOutputs()));
        self::assertSame("wrote to \$GLOBALS['cart']", $results[0]->getOutputs()[0]->getDescription());
        self::assertSame([Category::GLOBAL_VARIABLE], array_map(static fn ($finding) => $finding->getCategory(), $results[1]->getOutputs()));
        self::assertSame([Category::STATIC_VARIABLE], array_map(static fn ($finding) => $finding->getCategory(), $results[2]->getOutputs()));
    }

    public function testOriginalIssueBindingsActuallyChangeTheCallerObjects(): void
    {
        $code = <<<'PHP'
            require 'tests/Fixtures/argument-origins.php';
            $counts = [];
            foreach (['origin_direct', 'origin_local', 'origin_foreach', 'origin_keyed_foreach', 'origin_destructure', 'origin_list'] as $function) {
                $cart = new OriginCart(); $cart->items = ['shoes'];
                $function([$cart]);
                $counts[] = count($cart->items);
            }
            $cart = new OriginCart(); $cart->items = ['shoes']; origin_alias($cart);
            $counts[] = count($cart->items);
            $cart = new OriginCart(); $cart->parent = new OriginCart(); $cart->parent->items = ['shoes'];
            origin_clone($cart);
            $counts[] = count($cart->parent->items);
            $cart = new OriginCart(); $cart->items = ['shoes']; origin_clone_own($cart);
            $counts[] = count($cart->items);
            echo json_encode($counts);
            PHP;
        self::assertSame([0, '[0,0,0,0,0,0,0,0,1]', ''], Process::run([PHP_BINARY, '-r', $code]));
    }

    /** @return iterable<string, array{string, string}> */
    public function bindings(): iterable
    {
        yield 'assignment' => ['$local = $param[0];', '$local->items = [];'];
        yield 'foreach' => ['foreach ($param as $local) {', '$local->items = []; }'];
        yield 'keyed foreach' => ['foreach ($param as $key => $local) {', '$local->items = []; }'];
        yield 'destructure' => ['[$local] = $param;', '$local->items = [];'];
        yield 'list' => ['list($local) = $param;', '$local->items = [];'];
        yield 'keyed destructure' => ['["cart" => $local] = $param;', '$local->items = [];'];
        yield 'nested destructure' => ['[[$local]] = $param;', '$local->items = [];'];
        yield 'alias' => ['$local = $param;', '$local->items = [];'];
        yield 'chain' => ['$first = $param; $local = $first;', '$local->items = [];'];
        yield 'shallow clone' => ['$local = clone $param;', '$local->parent->items = [];'];
        yield 'array in clone' => ['$local = clone $param;', '$local->items[0]->x = 1;'];
        yield 'object in copied array' => ['$local = $param;', '$local[0]->x = 1;'];
        yield 'property selection' => ['$local = $param->parent;', '$local->x = 1;'];
        yield 'nullsafe property selection' => ['$local = $param?->parent;', '$local->x = 1;'];
        yield 'clone property selection' => ['$copy = clone $param; $local = $copy->parent;', '$local->x = 1;'];
        yield 'clone array selection' => ['$copy = clone $param; $items = $copy->items; $local = $items[0];', '$local->x = 1;'];
        yield 'foreach destructuring' => ['foreach ($param as [$local]) {', '$local->x = 1; }'];
        yield 'foreach key shadows iterable' => ['$items = $param; foreach ($items as $items => $local) {', '$local->x = 1; }'];
        yield 'reference handle' => ['$local = &$param;', '$local->x = 1;'];
    }
}
