<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\Cli\Severity;
use JonBaldie\ExplicitnessChecker\Finding;
use PHPUnit\Framework\TestCase;

/**
 * Every category has a severity, so a new category can't ship without one
 * (#72).
 */
class SeverityTest extends TestCase
{
    protected const EXIT_CODES = [
        'standardOutput' => 1,
        'globalVariable' => 2,
        'superglobal' => 2,
        'globalsArray' => 2,
        'objectProperty' => 2,
        'staticProperty' => 2,
        'staticCall' => 2,
        'argumentMutation' => 2,
        'staticVariable' => 2,
        'capturedReference' => 2,
        'file' => 3,
        'fileSystem' => 3,
        'environment' => 3,
        'time' => 3,
        'random' => 3,
        'httpHeaders' => 3,
        'errorLog' => 3,
        'session' => 3,
        'network' => 3,
        'database' => 3,
        'process' => 3,
        'mail' => 3,
        'include' => 3,
        'runtimeConfig' => 3,
    ];

    public function testEveryCategoryHasItsSeverity(): void
    {
        $expected = array_keys(self::EXIT_CODES);
        $actual = Category::ALL;
        sort($expected);
        sort($actual);
        self::assertSame($expected, $actual);

        foreach (self::EXIT_CODES as $category => $exitCode) {
            $severity = Severity::of([new Finding('a finding', $category, 1)]);
            self::assertSame($exitCode, Severity::EXIT_CODES[$severity], $category);
        }
    }
}
