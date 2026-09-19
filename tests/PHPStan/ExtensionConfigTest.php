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

    public function testStrictAndPropsAreOffByDefault(): void
    {
        [$exitCode, $output] = $this->analyse('extension.neon', 'test-fixtures/strict-examples.php');

        self::assertSame(0, $exitCode, $output);
    }

    public function testStrictParameterTurnsOnStrictMode(): void
    {
        [$exitCode, $output] = $this->analyse('extension-with-strict.neon', 'test-fixtures/strict-examples.php');

        self::assertSame(1, $exitCode, $output);
        self::assertStringContainsString(
            'strict-examples.php:105:get_database_url reads from environment variables (getenv). '
            . '[identifier=explicitness.environment]',
            $output,
        );
        self::assertSame(29, substr_count($output, '[identifier=explicitness.'), $output);
    }

    public function testPropsParameterTurnsOnPropsMode(): void
    {
        [$exitCode, $output] = $this->analyse('extension-with-props.neon', 'test-fixtures/strict-examples.php');

        self::assertSame(1, $exitCode, $output);
        self::assertStringContainsString(
            'strict-examples.php:24:UserSession::__construct wrote to object property $this->username. '
            . '[identifier=explicitness.objectProperty]',
            $output,
        );
        self::assertSame(8, substr_count($output, '[identifier=explicitness.'), $output);
    }

    public function testStrictAndPropsCombine(): void
    {
        [$exitCode, $output] = $this->analyse('extension-with-strict-and-props.neon', 'test-fixtures/strict-examples.php');

        self::assertSame(1, $exitCode, $output);
        self::assertStringContainsString('[identifier=explicitness.environment]', $output);
        self::assertStringContainsString('[identifier=explicitness.objectProperty]', $output);
        self::assertSame(37,substr_count($output, '[identifier=explicitness.'), $output);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public function invalidConfigurations(): iterable
    {
        yield 'non-boolean strict' => [
            'extension-with-non-boolean-strict.neon',
            "/The item 'parameters\\W+explicitness\\W+strict' expects to be bool/u",
        ];
        yield 'misspelled strict' => [
            'extension-with-misspelled-strict.neon',
            "/Unexpected item 'parameters\\W+explicitness\\W+strcit'/u",
        ];
        yield 'non-boolean props' => [
            'extension-with-non-boolean-props.neon',
            "/The item 'parameters\\W+explicitness\\W+props' expects to be bool/u",
        ];
    }

    /**
     * @dataProvider invalidConfigurations
     */
    public function testInvalidConfigurationIsRejected(string $config, string $pattern): void
    {
        [$exitCode, $output] = $this->analyse($config, 'test-fixtures/good-examples.php');

        self::assertSame(1, $exitCode, $output);
        self::assertMatchesRegularExpression($pattern, $output);
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
