<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Tests\Support\Process;
use PHPUnit\Framework\TestCase;

/**
 * Which function-likes the real CLI checks, and what it calls them (#8, #12).
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
        [$exitCode, $rows] = $this->runCli($fixture);

        self::assertSame(
            [
                [(string) (9 + $offset), $namespace . 'scope_conditional', 'read from global variable $conditional', ''],
                [(string) (18 + $offset), 'class@anonymous::anonymousMethod', 'read from superglobal $_GET', ''],
                [(string) (26 + $offset), $namespace . 'ScopeNamed::namedMethod', 'read from global variable $named', ''],
                [(string) (35 + $offset), '{closure}', 'read from superglobal $_POST', ''],
                [(string) (38 + $offset), '{closure}', 'read from superglobal $_COOKIE', ''],
                [(string) (44 + $offset), '{closure}', 'read from superglobal $_GET', ''],
            ],
            $rows,
        );
        self::assertSame(2, $exitCode);
    }

    /**
     * #8: a `global` inside a nested function-like doesn't make the enclosing
     * function's local of the same name a global.
     */
    public function testGlobalInNestedFunctionLikeDoesNotBleedIntoEnclosingFunction(): void
    {
        [$exitCode, $rows] = $this->runCli('closure-global-bleed.php');

        self::assertSame(
            [
                ['12', '{closure}', 'read from global variable $x', ''],
                ['24', 'nested_reads_global', 'read from global variable $y', ''],
                ['38', 'class@anonymous::readsGlobal', 'read from global variable $z', ''],
            ],
            $rows,
        );
        self::assertSame(2, $exitCode);
    }

    /**
     * #27: a property hook is a function-like with a body, so it's checked on
     * its own, named after its class, property and hook kind. `{closure}` is
     * still only for closures and arrow functions, including those in a hook.
     */
    public function testPropertyHooksAreNamedAfterTheirPropertyAndHookKind(): void
    {
        [$exitCode, $rows] = $this->runCli('property-hooks.php', ['--props']);

        self::assertSame(
            [
                ['15', 'App\\Sub\\Temperature::$celsius::get', 'read from object property $this->celsius', ''],
                ['16', 'App\\Sub\\Temperature::$celsius::set', '', 'wrote to object property $this->celsius'],
                ['22', 'App\\Sub\\Temperature::$source::get', 'read from superglobal $_GET', ''],
                ['26', 'App\\Sub\\Temperature::$label::get', 'read from object property $this->label', ''],
                ['33', 'class@anonymous::$reading::get', 'read from superglobal $_SERVER', ''],
                ['34', '{closure}', 'read from superglobal $_POST', ''],
            ],
            $rows,
        );
        self::assertSame(2, $exitCode);
    }

    /**
     * Runs the CLI on a fixture and returns its exit code and table rows as
     * [line, function, implicit inputs, implicit outputs].
     *
     * @param list<string> $flags
     *
     * @return array{int, list<list<string>>}
     */
    protected function runCli(string $fixture, array $flags = []): array
    {
        $path = Process::ROOT . '/test-fixtures/' . $fixture;
        [$exitCode, $output, $errors] = Process::cli(array_merge($flags, [$path]));
        self::assertSame('', $errors);

        $rows = [];
        foreach (explode("\n", $output) as $line) {
            $cells = array_map('trim', explode('|', $line));
            if (count($cells) !== 8 || $cells[1] !== $path) {
                continue;
            }
            $rows[] = [$cells[2], $cells[3], $cells[4], $cells[5]];
        }

        return [$exitCode, $rows];
    }
}
