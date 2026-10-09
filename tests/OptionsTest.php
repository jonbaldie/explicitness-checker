<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Cli\ExplicitnessMinimum;
use JonBaldie\ExplicitnessChecker\Cli\FileFilter;
use JonBaldie\ExplicitnessChecker\Cli\Options;
use JonBaldie\ExplicitnessChecker\Mode;
use PHPUnit\Framework\TestCase;

class OptionsTest extends TestCase
{
    protected const MISSING_PATH = __DIR__ . '/no-such-path';

    protected const UNCLOSED_GROUP = 'Compilation failed: missing closing parenthesis at offset 1';

    public function testOptionsWithAnExistingPathAndValidSettingsHaveNoProblem(): void
    {
        self::assertNull($this->options(__DIR__)->problem());
        self::assertNull($this->options(__FILE__, minimum: '87.5')->problem());
    }

    public function testAPathThatIsNeitherAFileNorADirectoryIsNotFound(): void
    {
        self::assertSame('Path not found: ' . self::MISSING_PATH, $this->options(self::MISSING_PATH)->problem());
    }

    public function testAnEmptyExcludedDirectoryIsInvalid(): void
    {
        self::assertSame(
            'Invalid --exclude: directory name is empty',
            $this->options(__DIR__, excludeDirs: ['vendor', '/'])->problem(),
        );
    }

    public function testAPatternThatDoesNotCompileIsInvalidUnderItsOwnFlag(): void
    {
        self::assertSame(
            'Invalid --include-pattern: ' . self::UNCLOSED_GROUP,
            $this->options(__DIR__, includePatterns: ['src', '('])->problem(),
        );
        self::assertSame(
            'Invalid --exclude-pattern: ' . self::UNCLOSED_GROUP,
            $this->options(__DIR__, excludePatterns: ['('])->problem(),
        );
    }

    public function testAMinimumAboveOneHundredIsInvalid(): void
    {
        self::assertSame('Invalid --min-explicitness: 101', $this->options(__DIR__, minimum: '101')->problem());
    }

    public function testAMissingPathIsReportedBeforeEveryOtherProblem(): void
    {
        $options = $this->options(
            self::MISSING_PATH,
            excludeDirs: ['/'],
            includePatterns: ['('],
            excludePatterns: ['('],
            minimum: '101',
        );

        self::assertSame('Path not found: ' . self::MISSING_PATH, $options->problem());
    }

    public function testAnEmptyExcludedDirectoryIsReportedBeforeBadPatternsAndMinimum(): void
    {
        $options = $this->options(
            __DIR__,
            excludeDirs: ['/'],
            includePatterns: ['('],
            excludePatterns: ['('],
            minimum: '101',
        );

        self::assertSame('Invalid --exclude: directory name is empty', $options->problem());
    }

    public function testABadIncludePatternIsReportedBeforeABadExcludePatternAndMinimum(): void
    {
        $options = $this->options(__DIR__, includePatterns: ['('], excludePatterns: ['['], minimum: '101');

        self::assertSame('Invalid --include-pattern: ' . self::UNCLOSED_GROUP, $options->problem());
    }

    public function testABadExcludePatternIsReportedBeforeABadMinimum(): void
    {
        $options = $this->options(__DIR__, excludePatterns: ['('], minimum: '101');

        self::assertSame('Invalid --exclude-pattern: ' . self::UNCLOSED_GROUP, $options->problem());
    }

    /**
     * @param list<string> $excludeDirs
     * @param list<string> $includePatterns
     * @param list<string> $excludePatterns
     */
    protected function options(
        string $path,
        array $excludeDirs = FileFilter::DEFAULT_EXCLUDE_DIRS,
        array $includePatterns = [],
        array $excludePatterns = [],
        ?string $minimum = null,
    ): Options {
        return new Options(
            $path,
            false,
            new Mode(false, false),
            new FileFilter($excludeDirs, $includePatterns, $excludePatterns),
            $minimum === null ? null : new ExplicitnessMinimum($minimum),
        );
    }
}
