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

    /**
     * Regression for #24: two same-basename files must produce distinct File
     * cells that are the walked paths, not basename().
     */
    public function testSameBasenameFilesAreDistinguishable(): void
    {
        $files = $this->fileCells($this->runApplication(['tests/Fixtures/same-basename']));
        sort($files);

        self::assertSame(
            [
                'tests/Fixtures/same-basename/gen/nested/Calculator.php',
                'tests/Fixtures/same-basename/src/Calculator.php',
            ],
            $files,
        );
    }

    /**
     * Regression for #24: a single-file run shows the walked path, not a
     * shorter invented name.
     */
    public function testSingleFileRunShowsTheWalkedPath(): void
    {
        self::assertSame(
            ['tests/Fixtures/same-basename/src/Calculator.php'],
            $this->fileCells($this->runApplication(['tests/Fixtures/same-basename/src/Calculator.php'])),
        );
    }

    public function testExitAndDieDescriptionsUseTheirArgumentSemantics(): void
    {
        $rows = [];
        foreach (explode("\n", $this->runApplication(['--strict', 'tests/Fixtures/exit-forms.php'])) as $line) {
            $cells = array_map('trim', explode('|', $line));
            if (count($cells) === 8 && (str_contains($cells[3], 'exit') || str_contains($cells[3], 'die'))) {
                $rows[$cells[3]] = [$cells[5], $cells[6]];
            }
        }

        self::assertSame(
            [
                'exit_with_status' => ['terminates the program (exit)', 'Minor'],
                'exit_without_status' => ['terminates the program (exit)', 'Minor'],
                'exit_with_message' => ['writes to standard output (exit)', 'Minor'],
                'exit_with_dynamic_value' => ['terminates the program (exit)', 'Minor'],
                'die_with_status' => ['terminates the program (die)', 'Minor'],
                'die_without_status' => ['terminates the program (die)', 'Minor'],
                'die_with_message' => ['writes to standard output (die)', 'Minor'],
                'die_with_dynamic_value' => ['terminates the program (die)', 'Minor'],
                'qualified_exit_with_status' => ['terminates the program (exit)', 'Minor'],
                'qualified_exit_with_message' => ['writes to standard output (exit)', 'Minor'],
                'qualified_exit_with_dynamic_value' => ['terminates the program (exit)', 'Minor'],
                'qualified_die_with_status' => ['terminates the program (die)', 'Minor'],
                'qualified_die_with_message' => ['writes to standard output (die)', 'Minor'],
                'qualified_die_with_dynamic_value' => ['terminates the program (die)', 'Minor'],
            ],
            $rows,
        );
    }

    /**
     * Regression for #26: every --exclude-pattern given is applied, not just
     * the last one, so a file matching an earlier pattern is still skipped.
     */
    public function testRepeatedExcludePatternsAllApply(): void
    {
        $files = $this->fileCells($this->runApplication(
            ['--exclude-pattern=gen/', '--exclude-pattern=src/', 'tests/Fixtures/same-basename'],
        ));

        self::assertSame([], $files);
    }

    /**
     * Regression for #26: every --include-pattern given is applied, so a file
     * matching any of them is analysed.
     */
    public function testRepeatedIncludePatternsAllApply(): void
    {
        $files = $this->fileCells($this->runApplication(
            ['--include-pattern=gen/', '--include-pattern=src/', 'tests/Fixtures/same-basename'],
        ));
        sort($files);

        self::assertSame(
            [
                'tests/Fixtures/same-basename/gen/nested/Calculator.php',
                'tests/Fixtures/same-basename/src/Calculator.php',
            ],
            $files,
        );
    }

    /**
     * @param list<string> $arguments
     */
    protected function runApplication(array $arguments): string
    {
        $out = fopen('php://memory', 'w+');
        $err = fopen('php://memory', 'w+');
        self::assertIsResource($out);
        self::assertIsResource($err);

        $cwd = getcwd();
        self::assertIsString($cwd);
        chdir(Process::ROOT);
        try {
            (new Application($out, $err))->run(array_merge(['bin/explicitness-checker'], $arguments));
        } finally {
            chdir($cwd);
        }

        rewind($out);

        return (string) stream_get_contents($out);
    }

    /**
     * @return list<string>
     */
    protected function fileCells(string $stdout): array
    {
        $files = [];
        foreach (explode("\n", $stdout) as $line) {
            $cells = array_map('trim', explode('|', $line));
            if (count($cells) === 8 && $cells[1] !== '' && $cells[1] !== 'File') {
                $files[] = $cells[1];
            }
        }

        return $files;
    }

    /**
     * Regression for #48: an unreadable file must emit a controlled diagnostic
     * on standard error without a raw PHP warning, skip the file, and continue
     * analysing readable siblings.
     */
    public function testUnreadableFileIsSkippedWithStderrDiagnostic(): void
    {
        $dir = sys_get_temp_dir() . '/ec_unreadable_test_' . uniqid();
        mkdir($dir);
        $unreadable = $dir . '/unreadable.php';
        $readable = $dir . '/readable.php';
        touch($unreadable);
        chmod($unreadable, 0000);
        file_put_contents($readable, "<?php\nfunction clean(): int { return 42; }\n");

        $out = fopen('php://memory', 'w+');
        $err = fopen('php://memory', 'w+');
        self::assertIsResource($out);
        self::assertIsResource($err);

        try {
            $exitCode = (new Application($out, $err))->run(['bin/explicitness-checker', $dir]);
            rewind($out);
            rewind($err);
            $stdout = (string) stream_get_contents($out);
            $stderr = (string) stream_get_contents($err);

            self::assertSame(0, $exitCode);
            self::assertSame("Cannot read file: {$unreadable}\n", $stderr);
            self::assertStringContainsString('No implicit inputs or outputs found.', $stdout);

            $directOut = fopen('php://memory', 'w+');
            $directErr = fopen('php://memory', 'w+');
            self::assertIsResource($directOut);
            self::assertIsResource($directErr);

            $directExitCode = (new Application($directOut, $directErr))->run(['bin/explicitness-checker', $unreadable]);
            rewind($directOut);
            rewind($directErr);
            self::assertSame(0, $directExitCode);
            self::assertSame("Cannot read file: {$unreadable}\n", (string) stream_get_contents($directErr));
            self::assertSame("No implicit inputs or outputs found.\n", (string) stream_get_contents($directOut));
        } finally {
            chmod($unreadable, 0644);
            unlink($unreadable);
            unlink($readable);
            rmdir($dir);
        }
    }
}

