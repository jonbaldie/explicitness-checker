<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Cli\FileChecker;
use JonBaldie\ExplicitnessChecker\Cli\Violation;
use JonBaldie\ExplicitnessChecker\FunctionResult;
use JonBaldie\ExplicitnessChecker\Mode;
use JonBaldie\ExplicitnessChecker\SourceChecker;
use JonBaldie\ExplicitnessChecker\Tests\Support\Process;
use PHPUnit\Framework\TestCase;

/**
 * The CLI's programmatic seam (#56): checking a file needs no Console and
 * returns data, so callers can decide whether and how to narrate it.
 */
class FileCheckerTest extends TestCase
{
    protected const GLOBAL_DECLARATION = Process::ROOT . '/test-fixtures/read-write-context/global-declaration.php';

    public function testReturnsEveryFunctionLikeWithItsAnalysis(): void
    {
        $result = (new FileChecker(new SourceChecker(), new Mode(false, false)))->check(self::GLOBAL_DECLARATION);

        self::assertSame(self::GLOBAL_DECLARATION, $result->getFile());
        self::assertFalse($result->hasParseError());
        self::assertFalse($result->isUnreadable());
        self::assertSame(
            [
                ['writeOnly', ['counter'], []],
                ['declaredButUnused', ['unused'], []],
                ['readAndWrite', ['total'], []],
            ],
            array_map(
                static fn (FunctionResult $function): array => [
                    $function->getName(),
                    $function->getAnalysis()->getDeclaredGlobals(),
                    $function->getAnalysis()->getParameters(),
                ],
                $result->getFunctions(),
            ),
        );
        self::assertSame(3, $result->getChecked());
    }

    public function testViolationsAreTheFunctionLikesWithFindings(): void
    {
        $result = (new FileChecker(new SourceChecker(), new Mode(false, false)))
            ->check(Process::ROOT . '/test-fixtures/good-examples.php');

        self::assertSame([], $result->getViolations());
        self::assertGreaterThan(0, $result->getChecked());

        $violations = (new FileChecker(new SourceChecker(), new Mode(false, false)))
            ->check(self::GLOBAL_DECLARATION)
            ->getViolations();

        self::assertSame(
            [
                [self::GLOBAL_DECLARATION, 'writeOnly', [], ['wrote to global variable $counter']],
                [
                    self::GLOBAL_DECLARATION,
                    'readAndWrite',
                    ['read from global variable $total'],
                    ['wrote to global variable $total'],
                ],
            ],
            array_map(
                static fn (Violation $violation): array => [
                    $violation->getFile(),
                    $violation->getFunction(),
                    $violation->getInputs(),
                    $violation->getOutputs(),
                ],
                $violations,
            ),
        );
    }

    public function testParseErrorIsFlaggedWithTheParserMessage(): void
    {
        $file = Process::ROOT . '/tests/Fixtures/cli/parse-error.php';
        $result = (new FileChecker(new SourceChecker(), new Mode(false, false)))->check($file);

        self::assertTrue($result->hasParseError());
        self::assertFalse($result->isUnreadable());
        self::assertSame('Syntax error, unexpected \'{\', expecting T_VARIABLE on line 4', $result->getParseError());
        self::assertSame([], $result->getFunctions());
        self::assertSame(0, $result->getChecked());
    }

    public function testUnreadableFileIsFlaggedWithoutAParseError(): void
    {
        $file = Process::ROOT . '/tests/Fixtures/cli/does-not-exist.php';
        $result = (new FileChecker(new SourceChecker(), new Mode(false, false)))->check($file);

        self::assertTrue($result->isUnreadable());
        self::assertFalse($result->hasParseError());
        self::assertNull($result->getParseError());
        self::assertSame([], $result->getFunctions());
        self::assertSame(0, $result->getChecked());
    }
}
