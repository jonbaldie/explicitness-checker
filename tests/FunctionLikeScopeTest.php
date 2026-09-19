<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Which function-likes the real CLI checks, and what it calls them (#8, #12).
 */
class FunctionLikeScopeTest extends TestCase
{
    protected const ROOT = __DIR__ . '/..';

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
     * Runs the CLI on a fixture and returns its exit code and table rows as
     * [line, function, implicit inputs, implicit outputs].
     *
     * @return array{int, list<list<string>>}
     */
    protected function runCli(string $fixture): array
    {
        $command = [PHP_BINARY, self::ROOT . '/bin/explicitness-checker', self::ROOT . '/test-fixtures/' . $fixture];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, self::ROOT);
        self::assertIsResource($process);
        $output = (string) stream_get_contents($pipes[1]);
        $errors = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        self::assertSame('', $errors);

        $rows = [];
        foreach (explode("\n", $output) as $line) {
            $cells = array_map('trim', explode('|', $line));
            if (count($cells) !== 8 || $cells[1] !== $fixture) {
                continue;
            }
            $rows[] = [$cells[2], $cells[3], $cells[4], $cells[5]];
        }

        return [$exitCode, $rows];
    }
}
