<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Tests\Support\Process;
use PHPUnit\Framework\TestCase;

/**
 * Runs the real CLI script and PHPStan as subprocesses.
 */
class CliScriptTest extends TestCase
{
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
        [$exitCode, $output] = $this->combined(Process::cli(array_merge($flags, [Process::ROOT . '/test-fixtures/' . $fixture])));

        self::assertSame($expectedExitCode, $exitCode, $output);
    }

    public function testCliWithoutPathPrintsUsage(): void
    {
        [$exitCode, $output] = $this->combined(Process::cli([]));

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Usage: explicitness-checker', $output);
    }

    /**
     * Regression test for #11: PHPStan level 6 reported "$argv might not be
     * defined" when register_argc_argv was off.
     */
    public function testPhpstanLevelSixPassesWithoutRegisteredArgv(): void
    {
        [$exitCode, $output] = $this->combined(Process::run([
            PHP_BINARY,
            '-d',
            'register_argc_argv=0',
            '-d',
            'phpstan.restarted=1',
            Process::ROOT . '/tests/Support/phpstan-without-argv.php',
        ]));

        self::assertStringNotContainsString('argv', $output);
        self::assertSame(0, $exitCode, $output);
    }

    /**
     * @param array{int, string, string} $result exit code, stdout, stderr
     *
     * @return array{int, string} exit code, stdout then stderr
     */
    protected function combined(array $result): array
    {
        return [$result[0], $result[1] . $result[2]];
    }
}
