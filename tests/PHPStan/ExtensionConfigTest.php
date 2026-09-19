<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests\PHPStan;

use PHPUnit\Framework\TestCase;

/**
 * Runs real `phpstan analyse` with extension.neon included, as a user would.
 */
class ExtensionConfigTest extends TestCase
{
    protected const ROOT = __DIR__ . '/../..';

    public function testExtensionReportsBadExamplesWithIdentifiers(): void
    {
        [$exitCode, $output] = $this->analyse('extension.neon', 'test-fixtures/bad-examples.php');

        self::assertSame(1, $exitCode, $output);
        self::assertStringContainsString(
            'bad-examples.php:34:uses_global_var read from global variable $some_global_number. '
            . '[identifier=explicitness.globalVariable]',
            $output,
        );
        self::assertSame(15, substr_count($output, '[identifier=explicitness.'), $output);
    }

    public function testExtensionReportsNothingOnGoodExamples(): void
    {
        [$exitCode, $output] = $this->analyse('extension.neon', 'test-fixtures/good-examples.php');

        self::assertSame(0, $exitCode, $output);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public function undeclaredParameters(): iterable
    {
        yield 'strict' => ['extension-with-strict.neon'];
        yield 'props' => ['extension-with-props.neon'];
    }

    /**
     * Until their own tickets declare them, setting strict or props fails
     * rather than being silently ignored.
     *
     * @dataProvider undeclaredParameters
     */
    public function testUndeclaredParameterIsRejected(string $config): void
    {
        [$exitCode, $output] = $this->analyse($config, 'test-fixtures/good-examples.php');

        self::assertSame(1, $exitCode, $output);
        self::assertMatchesRegularExpression("/Unexpected item 'parameters\\W+explicitness'/u", $output);
    }

    /**
     * @return array{int, string}
     */
    protected function analyse(string $config, string $path): array
    {
        $command = [
            PHP_BINARY,
            self::ROOT . '/vendor/bin/phpstan',
            'analyse',
            '--no-progress',
            // RawErrorFormatter only prints "[identifier=...]" when verbose,
            // or when it detects it's running under an agent (env vars such
            // as CLAUDECODE) — force it on so this test doesn't depend on
            // who/what is running it.
            '--verbose',
            '--error-format=raw',
            '--configuration=' . __DIR__ . '/../Support/' . $config,
            self::ROOT . '/' . $path,
        ];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, self::ROOT);
        self::assertIsResource($process);
        $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output];
    }
}
