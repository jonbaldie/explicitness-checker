<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Tests\Support\CheckedFile;
use JonBaldie\ExplicitnessChecker\Tests\Support\Process;
use PHPUnit\Framework\TestCase;

/**
 * Which function-likes the CLI checks, and what it calls them (#8, #12).
 */
class FunctionLikeScopeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public function scopeFixtures(): iterable
    {
        yield 'without namespace' => ['scope-global.php', ''];
        yield 'with namespace' => ['scope-namespaced.php', 'App\\Sub\\'];
    }

    /**
     * #12: the same code is checked the same way with or without a namespace,
     * and names are fully qualified. #8: closures are checked on their own.
     *
     * @dataProvider scopeFixtures
     */
    public function testChecksEveryFunctionLikeWithQualifiedNames(string $fixture, string $namespace): void
    {
        $offset = $namespace === '' ? 0 : 2;
        $rows = $this->rows($fixture);

        self::assertSame(
            [
                [9 + $offset, $namespace . 'scope_conditional', ['read from global variable $conditional'], []],
                [18 + $offset, 'class@anonymous::anonymousMethod', ['read from superglobal $_GET'], []],
                [26 + $offset, $namespace . 'ScopeNamed::namedMethod', ['read from global variable $named'], []],
                [35 + $offset, '{closure}', ['read from superglobal $_POST'], []],
                [38 + $offset, '{closure}', ['read from superglobal $_COOKIE'], []],
                [44 + $offset, '{closure}', ['read from superglobal $_GET'], []],
            ],
            $rows,
        );
    }

    /**
     * #8: a `global` inside a nested function-like doesn't make the enclosing
     * function's local of the same name a global.
     */
    public function testGlobalInNestedFunctionLikeDoesNotBleedIntoEnclosingFunction(): void
    {
        $rows = $this->rows('closure-global-bleed.php');

        self::assertSame(
            [
                [12, '{closure}', ['read from global variable $x'], []],
                [24, 'nested_reads_global', ['read from global variable $y'], []],
                [38, 'class@anonymous::readsGlobal', ['read from global variable $z'], []],
            ],
            $rows,
        );
    }

    /**
     * #27: a property hook is a function-like with a body, so it's checked on
     * its own, named after its class, property and hook kind. `{closure}` is
     * still only for closures and arrow functions, including those in a hook.
     */
    public function testPropertyHooksAreNamedAfterTheirPropertyAndHookKind(): void
    {
        $rows = $this->rows('property-hooks.php', ['--props']);

        self::assertSame(
            [
                [15, 'App\\Sub\\Temperature::$celsius::get', ['read from object property $this->celsius'], []],
                [16, 'App\\Sub\\Temperature::$celsius::set', [], ['wrote to object property $this->celsius']],
                [22, 'App\\Sub\\Temperature::$source::get', ['read from superglobal $_GET'], []],
                [26, 'App\\Sub\\Temperature::$label::get', ['read from object property $this->label'], []],
                [33, 'class@anonymous::$reading::get', ['read from superglobal $_SERVER'], []],
                [34, '{closure}', ['read from superglobal $_POST'], []],
            ],
            $rows,
        );
    }

    /**
     * #47: anonymous classes nested in different named classes need distinct
     * names for both methods and property hooks.
     */
    public function testNestedAnonymousClassesKeepTheirNamedEnclosingClass(): void
    {
        $rows = CheckedFile::rows(Process::ROOT . '/tests/Fixtures/nested-anonymous-classes.php');

        self::assertSame(
            [
                [10, 'App\\ServiceA::class@anonymous::send', ['read from superglobal $_GET'], []],
                [16, 'App\\ServiceA::class@anonymous::$value::get', ['read from superglobal $_GET'], []],
                [27, 'App\\ServiceB::class@anonymous::send', ['read from superglobal $_GET'], []],
                [33, 'App\\ServiceB::class@anonymous::$value::get', ['read from superglobal $_GET'], []],
            ],
            $rows,
        );
    }

    /**
     * Checks a fixture as the CLI does and returns its report rows as
     * [line, function, implicit inputs, implicit outputs].
     *
     * @param list<string> $flags
     *
     * @return list<array{int, string, list<string>, list<string>}>
     */
    protected function rows(string $fixture, array $flags = []): array
    {
        return CheckedFile::rows(Process::ROOT . '/test-fixtures/' . $fixture, $flags);
    }
}
