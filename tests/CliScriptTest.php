<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Tests\Support\Process;
use PHPUnit\Framework\TestCase;

/**
 * Runs the real CLI script and PHPStan as subprocesses.
 *
 * Subprocesses run unmutated code and aren't measured for coverage, so keeping
 * them out of the classes that cover src/ keeps them out of each mutant's run.
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
        yield 'strict, default' => [[], 'strict-examples.php', 2];
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
     * Regression test for #48: an unreadable file must emit a controlled diagnostic
     * on standard error without leaking raw PHP warnings or stack traces.
     */
    public function testUnreadableFileEmitsControlledStderrInSubprocess(): void
    {
        $dir = sys_get_temp_dir() . '/ec_cli_unreadable_' . uniqid();
        mkdir($dir);
        $unreadable = $dir . '/unreadable.php';
        touch($unreadable);
        chmod($unreadable, 0000);

        try {
            [$exitCode, $stdout, $stderr] = Process::cli([$unreadable]);
            self::assertSame(0, $exitCode);
            self::assertSame("Cannot read file: {$unreadable}\n", $stderr);
            self::assertSame("No implicit inputs or outputs found.\n", $stdout);
        } finally {
            chmod($unreadable, 0644);
            unlink($unreadable);
            rmdir($dir);
        }
    }

    /**
     * Runs bin/explicitness-checker as a subprocess on every golden CLI case.
     *
     * @dataProvider \JonBaldie\ExplicitnessChecker\Tests\CliOutputTest::cliCases
     *
     * @param list<string> $arguments
     */
    public function testBinScript(array $arguments, string $stdout, string $stderr, int $exitCode): void
    {
        self::assertSame(
            [$exitCode, $stdout, $stderr],
            Process::run(array_merge([PHP_BINARY, 'bin/explicitness-checker'], $arguments)),
        );
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
