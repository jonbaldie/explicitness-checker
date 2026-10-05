<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Cli\Application;
use JonBaldie\ExplicitnessChecker\Cli\ArgumentParser;
use JonBaldie\ExplicitnessChecker\Cli\Console;
use JonBaldie\ExplicitnessChecker\Cli\FileChecker;
use JonBaldie\ExplicitnessChecker\Cli\PhpFileFinder;
use JonBaldie\ExplicitnessChecker\Cli\Severity;
use JonBaldie\ExplicitnessChecker\SourceChecker;
use JonBaldie\ExplicitnessChecker\Tests\Support\CheckedFile;
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
        $files = $this->reportedFiles(['tests/Fixtures/same-basename']);
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
            $this->reportedFiles(['tests/Fixtures/same-basename/src/Calculator.php']),
        );
    }

    public function testExitAndDieDescriptionsUseTheirArgumentSemantics(): void
    {
        $rows = [];
        foreach (CheckedFile::violations(Process::ROOT . '/tests/Fixtures/exit-forms.php', ['--strict']) as $violation) {
            $rows[$violation->getFunction()] = [$violation->getOutputs(), $violation->getSeverity()];
        }

        self::assertSame(
            [
                'exit_with_status' => [['terminates the program (exit)'], Severity::MINOR],
                'exit_without_status' => [['terminates the program (exit)'], Severity::MINOR],
                'exit_with_message' => [['writes to standard output (exit)'], Severity::MINOR],
                'exit_with_dynamic_value' => [['terminates the program (exit)'], Severity::MINOR],
                'die_with_status' => [['terminates the program (die)'], Severity::MINOR],
                'die_without_status' => [['terminates the program (die)'], Severity::MINOR],
                'die_with_message' => [['writes to standard output (die)'], Severity::MINOR],
                'die_with_dynamic_value' => [['terminates the program (die)'], Severity::MINOR],
                'qualified_exit_with_status' => [['terminates the program (exit)'], Severity::MINOR],
                'qualified_exit_with_message' => [['writes to standard output (exit)'], Severity::MINOR],
                'qualified_exit_with_dynamic_value' => [['terminates the program (exit)'], Severity::MINOR],
                'qualified_die_with_status' => [['terminates the program (die)'], Severity::MINOR],
                'qualified_die_with_message' => [['writes to standard output (die)'], Severity::MINOR],
                'qualified_die_with_dynamic_value' => [['terminates the program (die)'], Severity::MINOR],
                'mixed_case_die_with_status' => [['terminates the program (die)'], Severity::MINOR],
                'uppercase_die_with_message' => [['writes to standard output (die)'], Severity::MINOR],
                'mixed_case_exit_with_dynamic_value' => [['terminates the program (exit)'], Severity::MINOR],
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
        $files = $this->reportedFiles(
            ['--exclude-pattern=gen/', '--exclude-pattern=src/', 'tests/Fixtures/same-basename'],
        );

        self::assertSame([], $files);
    }

    /**
     * Regression for #26: every --include-pattern given is applied, so a file
     * matching any of them is analysed.
     */
    public function testRepeatedIncludePatternsAllApply(): void
    {
        $files = $this->reportedFiles(
            ['--include-pattern=gen/', '--include-pattern=src/', 'tests/Fixtures/same-basename'],
        );
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
     * The files the CLI would report rows for: the files it finds for the
     * arguments, checked with its options, as their File cells name them.
     * The golden cases pin how Application renders the same runs.
     *
     * @param list<string> $arguments
     *
     * @return list<string>
     */
    protected function reportedFiles(array $arguments): array
    {
        $options = (new ArgumentParser())->parse(array_merge(['bin/explicitness-checker'], $arguments));
        self::assertNotNull($options);
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        $cwd = getcwd();
        self::assertIsString($cwd);
        chdir(Process::ROOT);
        try {
            $files = (new PhpFileFinder($options->getFilter(), new Console($stream, $stream, false)))
                ->find($options->getPath());
            $checker = new FileChecker(new SourceChecker(), $options->getMode());
            $reported = [];
            foreach ($files as $file) {
                foreach ($checker->check($file)->getViolations() as $violation) {
                    $reported[] = $violation->getFile();
                }
            }
        } finally {
            chdir($cwd);
        }

        return $reported;
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

