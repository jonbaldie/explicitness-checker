<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Runs the real CLI script and PHPStan as subprocesses.
 */
class CliScriptTest extends TestCase
{
    protected const ROOT = __DIR__ . '/..';

    /**
     * @return iterable<string, array{list<string>, string, int}>
     */
    public function fixtureExitCodes(): iterable
    {
        yield 'bad, default' => [[], 'bad-examples.php', 2];
        yield 'bad, strict and props' => [['--strict', '--props'], 'bad-examples.php', 3];
        yield 'good, default' => [[], 'good-examples.php', 0];
        yield 'good, strict and props' => [['--strict', '--props'], 'good-examples.php', 0];
        yield 'strict, default' => [[], 'strict-examples.php', 0];
        yield 'strict, strict and props' => [['--strict', '--props'], 'strict-examples.php', 3];
    }

    /**
     * @dataProvider fixtureExitCodes
     *
     * @param list<string> $flags
     */
    public function testCliExitCodeOnFixture(array $flags, string $fixture, int $expectedExitCode): void
    {
        $command = array_merge(
            [PHP_BINARY, self::ROOT . '/bin/explicitness-checker'],
            $flags,
            [self::ROOT . '/test-fixtures/' . $fixture],
        );

        [$exitCode, $output] = $this->runCommand($command);

        self::assertSame($expectedExitCode, $exitCode, $output);
    }

    public function testCliWithoutPathPrintsUsage(): void
    {
        [$exitCode, $output] = $this->runCommand([PHP_BINARY, self::ROOT . '/bin/explicitness-checker']);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Usage: explicitness-checker', $output);
    }

    /**
     * Regression test for #11: PHPStan level 6 reported "$argv might not be
     * defined" when register_argc_argv was off.
     */
    public function testPhpstanLevelSixPassesWithoutRegisteredArgv(): void
    {
        [$exitCode, $output] = $this->runCommand([
            PHP_BINARY,
            '-d',
            'register_argc_argv=0',
            '-d',
            'phpstan.restarted=1',
            self::ROOT . '/tests/Support/phpstan-without-argv.php',
        ]);

        self::assertStringNotContainsString('argv', $output);
        self::assertSame(0, $exitCode, $output);
    }

    /**
     * @param list<string> $command
     *
     * @return array{int, string}
     */
    protected function runCommand(array $command): array
    {
        $stderr = tmpfile();
        self::assertIsResource($stderr);

        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => $stderr], $pipes, self::ROOT);
        self::assertIsResource($process);

        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exitCode = proc_close($process);

        rewind($stderr);
        $output .= (string) stream_get_contents($stderr);
        fclose($stderr);

        return [$exitCode, $output];
    }
}
