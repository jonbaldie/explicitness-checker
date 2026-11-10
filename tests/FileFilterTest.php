<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Cli\Application;
use JonBaldie\ExplicitnessChecker\Cli\FileFilter;
use PHPUnit\Framework\TestCase;

class FileFilterTest extends TestCase
{
    /**
     * @dataProvider invalidPatterns
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testInvalidPatternDiagnosticsDoNotDependOnTheCallersWarningHistory(
        string $flag,
        string $pattern,
        bool $staleWarning,
        string $reason,
    ): void {
        error_clear_last();
        if ($staleWarning) {
            @trigger_error('older unrelated warning', E_USER_WARNING);
        }
        $lastError = error_get_last();
        $handler = static fn (): bool => true;
        set_error_handler($handler);
        try {
            $filter = $flag === 'include'
                ? new FileFilter([], [$pattern])
                : new FileFilter([], [], [$pattern]);
            self::assertSame('Invalid --' . $flag . '-pattern: ' . $reason, $filter->error());
            self::assertSame($lastError, error_get_last());
            $out = fopen('php://memory', 'w+');
            $err = fopen('php://memory', 'w+');
            self::assertIsResource($out);
            self::assertIsResource($err);
            try {
                self::assertSame(2, (new Application($out, $err))->run([
                    'explicitness-checker', '--' . $flag . '-pattern=' . $pattern, __DIR__,
                ]));
                rewind($out);
                rewind($err);
                self::assertSame('', stream_get_contents($out));
                self::assertStringStartsWith(
                    'Invalid --' . $flag . '-pattern: ' . $reason . "\nUsage:",
                    (string) stream_get_contents($err),
                );
            } finally {
                fclose($out);
                fclose($err);
            }
            $restored = set_error_handler($handler);
            restore_error_handler();
            self::assertSame($handler, $restored);
        } finally {
            restore_error_handler();
        }
    }

    /** @return iterable<string, array{string, string, bool, string}> */
    public static function invalidPatterns(): iterable
    {
        foreach (['include', 'exclude'] as $flag) {
            foreach ([false, true] as $staleWarning) {
                foreach ([
                    '[' => 'Compilation failed: missing terminating ] for character class at offset 1',
                    '(' => 'Compilation failed: missing closing parenthesis at offset 1',
                ] as $pattern => $reason) {
                    yield $flag . '-' . $pattern . '-' . (int) $staleWarning => [$flag, $pattern, $staleWarning, $reason];
                }
            }
        }
    }

    public function testValidPatternsRestoreTheCallersThrowingErrorHandler(): void
    {
        $handler = static function (int $severity, string $message): void {
            throw new \ErrorException($message, 0, $severity);
        };
        set_error_handler($handler);
        try {
            self::assertNull((new FileFilter([], ['src/.*\\.php$'], ['vendor/']))->error());
            self::assertSame(
                'Invalid --include-pattern: Compilation failed: missing closing parenthesis at offset 1',
                (new FileFilter([], ['(']))->error(),
            );
            $restored = set_error_handler($handler);
            restore_error_handler();
            self::assertSame($handler, $restored);
            $this->expectException(\ErrorException::class);
            $this->expectExceptionMessage('caller handler still installed');
            trigger_error('caller handler still installed', E_USER_WARNING);
        } finally {
            restore_error_handler();
        }
    }

    public function testAFilterBuiltWithoutSettingsExcludesVendorAndNothingElse(): void
    {
        $filter = new FileFilter();

        self::assertTrue($filter->isInExcludedDirectory('vendor/package/src/Thing.php'));
        self::assertFalse($filter->isInExcludedDirectory('src/Thing.php'));
        self::assertTrue($filter->matchesPatterns('src/Thing.php'));
        self::assertSame(['Excluding directories: vendor'], $filter->describe());
    }
}
