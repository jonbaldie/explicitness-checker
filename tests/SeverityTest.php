<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\Cli\Severity;
use JonBaldie\ExplicitnessChecker\Finding;
use JonBaldie\ExplicitnessChecker\FunctionResult;
use JonBaldie\ExplicitnessChecker\Mode;
use JonBaldie\ExplicitnessChecker\SourceChecker;
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

    /**
     * `$_ENV` is environment access, so critical although it is reported as a
     * superglobal, whether read or written; other superglobals are serious.
     */
    public function testEnvSuperglobalIsCritical(): void
    {
        $source = <<<'PHP'
            <?php
            function readEnv(): string { return $_ENV['KEY']; }
            function writeEnv(): void { $_ENV['KEY'] = 'x'; }
            function readGet(): string { return $_GET['page']; }
            PHP;
        $severities = array_map(
            static fn (FunctionResult $result): string => Severity::of([...$result->getInputs(), ...$result->getOutputs()]),
            (new SourceChecker())->check($source, new Mode(false, false)),
        );
        self::assertSame([Severity::CRITICAL, Severity::CRITICAL, Severity::SERIOUS], $severities);
    }

    /**
     * Rewording a finding cannot change whether it counts as `$_ENV` (#36).
     */
    public function testEnvSeverityIgnoresTheDescription(): void
    {
        self::assertSame(Severity::CRITICAL, Severity::of([new Finding('reworded', Category::SUPERGLOBAL, 1, '_ENV')]));
        self::assertSame(Severity::SERIOUS, Severity::of([new Finding('read from superglobal $_ENV', Category::SUPERGLOBAL, 1)]));
        self::assertSame(Severity::SERIOUS, Severity::of([new Finding('read from superglobal $_ENV', Category::SUPERGLOBAL, 1, '_GET')]));
        self::assertSame(Severity::SERIOUS, Severity::of([new Finding('read from global variable $_ENV', Category::GLOBAL_VARIABLE, 1, '_ENV')]));
    }
}
