<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Cli\Application;
use JonBaldie\ExplicitnessChecker\Tests\Support\Process;
use PHPUnit\Framework\TestCase;

/**
 * Pins the CLI's stdout, stderr and exit code, byte for byte, for every case in
 * tests/Support/cli-cases.php against tests/Fixtures/cli-expected/, run
 * in-process. CliScriptTest runs the same cases through bin/explicitness-checker.
 */
class CliOutputTest extends TestCase
{
    protected const EXPECTED = Process::ROOT . '/tests/Fixtures/cli-expected';

    /**
     * @return iterable<string, array{list<string>, string, string, int}>
     */
    public static function cliCases(): iterable
    {
        /** @var array<string, list<string>> $cases */
        $cases = require Process::ROOT . '/tests/Support/cli-cases.php';
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
     * Runs the command in-process through Application::run, the entry point
     * bin/explicitness-checker hands over to, so coverage and mutation testing
     * see the CLI code.
     *
     * @dataProvider cliCases
     *
     * @param list<string> $arguments
     */
    public function testApplication(array $arguments, string $stdout, string $stderr, int $exitCode): void
    {
        $out = fopen('php://memory', 'w+');
        $err = fopen('php://memory', 'w+');
        self::assertIsResource($out);
        self::assertIsResource($err);

        $cwd = getcwd();
        self::assertIsString($cwd);
        chdir(Process::ROOT);
        try {
            $actualExitCode = (new Application($out, $err))->run(array_merge(['bin/explicitness-checker'], $arguments));
        } finally {
            chdir($cwd);
        }

        rewind($out);
        rewind($err);
        self::assertSame(
            [$exitCode, $stdout, $stderr],
            [$actualExitCode, (string) stream_get_contents($out), (string) stream_get_contents($err)],
        );
    }
}
