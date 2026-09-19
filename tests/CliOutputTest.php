<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Pins the CLI's stdout, stderr and exit code, byte for byte, for every case in
 * tests/Support/cli-cases.php against tests/Fixtures/cli-expected/.
 */
class CliOutputTest extends TestCase
{
    protected const ROOT = __DIR__ . '/..';
    protected const EXPECTED = self::ROOT . '/tests/Fixtures/cli-expected';

    /**
     * @return iterable<string, array{list<string>, string, string, int}>
     */
    public function cliCases(): iterable
    {
        /** @var array<string, list<string>> $cases */
        $cases = require self::ROOT . '/tests/Support/cli-cases.php';
        $expected = json_decode((string) file_get_contents(self::EXPECTED . '/expected.json'), true);
        self::assertIsArray($expected);

        foreach ($cases as $name => $arguments) {
            self::assertIsArray($expected[$name] ?? null, "No expected output recorded for case {$name}");
            yield $name => [
                $arguments,
                (string) file_get_contents(self::EXPECTED . '/' . $name . '.stdout'),
                (string) $expected[$name]['stderr'],
                (int) $expected[$name]['exitCode'],
            ];
        }
    }

    /**
     * Runs bin/explicitness-checker as a subprocess.
     *
     * @dataProvider cliCases
     *
     * @param list<string> $arguments
     */
    public function testBinScript(array $arguments, string $stdout, string $stderr, int $exitCode): void
    {
        $command = array_merge([PHP_BINARY, 'bin/explicitness-checker'], $arguments);
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, self::ROOT);
        self::assertIsResource($process);

        $actualStdout = (string) stream_get_contents($pipes[1]);
        $actualStderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        self::assertSame(
            [$exitCode, $stdout, $stderr],
            [proc_close($process), $actualStdout, $actualStderr],
        );
    }
}
