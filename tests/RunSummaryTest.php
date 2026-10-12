<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\Cli\ExplicitnessMinimum;
use JonBaldie\ExplicitnessChecker\Cli\DiscoveredFiles;
use JonBaldie\ExplicitnessChecker\Cli\FileCheckResult;
use JonBaldie\ExplicitnessChecker\SourceChecker;
use JonBaldie\ExplicitnessChecker\Mode;
use JonBaldie\ExplicitnessChecker\FunctionResult;
use JonBaldie\ExplicitnessChecker\FunctionAnalysis;
use JonBaldie\ExplicitnessChecker\Cli\RunSummary;
use JonBaldie\ExplicitnessChecker\Cli\UncheckedInput;
use JonBaldie\ExplicitnessChecker\Cli\Violation;
use JonBaldie\ExplicitnessChecker\Finding;
use PHPUnit\Framework\TestCase;

/**
 * The outcome of a run, without a Console (#77): what was found, how explicit
 * the checked function-likes were, and the exit code.
 */
class RunSummaryTest extends TestCase
{
    public function testAggregatesRealFileResultsInOrder(): void
    {
        $checker = new SourceChecker();
        $first = new FileCheckResult('first.php', $checker->check('<?php function one() { echo "a"; } function pure($a) { return $a; }', new Mode(true, false)));
        $second = new FileCheckResult('second.php', $checker->check('<?php function two() { global $a; return $a; }', new Mode(true, false)));
        $summary = RunSummary::of(new DiscoveredFiles(['first.php', 'second.php']), [$first, $second], new ExplicitnessMinimum('30'));

        self::assertEquals(array_merge($first->getViolations(), $second->getViolations()), $summary->getViolations());
        self::assertSame(3, $summary->getCheckedCount());
        self::assertSame(1, $summary->getExplicitCount());
        self::assertSame(333, $summary->getExplicitnessTenths());
        self::assertSame(0, $summary->getExitCode());
    }

    public function testPreservesDiscoveryThenFileFailureOrder(): void
    {
        $directory = new UncheckedInput('locked', UncheckedInput::UNREADABLE_DIRECTORY);
        $unreadable = new UncheckedInput('locked.php', UncheckedInput::UNREADABLE_FILE);
        $unparseable = self::unparseable();
        $summary = RunSummary::of(new DiscoveredFiles([], [$directory]), [
            new FileCheckResult('locked.php', [], $unreadable),
            new FileCheckResult('broken.php', [], $unparseable),
        ], null);

        self::assertSame([$directory, $unreadable, $unparseable], $summary->getUnchecked());
        self::assertSame(0, $summary->getCheckedCount());
        self::assertSame(2, $summary->getExitCode());
        $empty = RunSummary::of(new DiscoveredFiles([], [$directory]), [], null);
        self::assertSame([$directory], $empty->getUnchecked());
        self::assertSame(0, $empty->getExitCode());
    }

    public function testACleanRunExitsZero(): void
    {
        $summary = self::summary([], [], 4, null);

        self::assertSame(['minor' => 0, 'serious' => 0, 'critical' => 0], $summary->getSeverityCounts());
        self::assertSame(4, $summary->getCheckedCount());
        self::assertSame(4, $summary->getExplicitCount());
        self::assertSame(0, $summary->getExitCode());
    }

    public function testViolationsExitWithTheHighestSeverityFound(): void
    {
        $summary = self::summary(
            [self::violation(Category::STANDARD_OUTPUT), self::violation(Category::GLOBAL_VARIABLE), self::violation(Category::STANDARD_OUTPUT)],
            [],
            5,
            null,
        );

        self::assertSame(['minor' => 2, 'serious' => 1, 'critical' => 0], $summary->getSeverityCounts());
        self::assertSame(2, $summary->getExplicitCount());
        self::assertSame(2, $summary->getExitCode());
        self::assertSame(1, (self::summary([self::violation(Category::STANDARD_OUTPUT)], [], 1, null))->getExitCode());
        self::assertSame(3, (self::summary([self::violation(Category::FILE), self::violation(Category::GLOBAL_VARIABLE)], [], 2, null))->getExitCode());
    }

    public function testParseErrorsExitAtLeastSerious(): void
    {
        self::assertSame(2, (self::summary([], [self::unparseable()], 0, null))->getExitCode());
        self::assertSame(2, (self::summary([self::violation(Category::STANDARD_OUTPUT)], [self::unparseable()], 1, null))->getExitCode());
        self::assertSame(3, (self::summary([self::violation(Category::FILE)], [self::unparseable()], 1, null))->getExitCode());
    }

    /**
     * 3 of 4 checked function-likes are explicit: 75%.
     */
    public function testMeetingTheMinimumStopsViolationsSettingTheExitCode(): void
    {
        $met = self::summary([self::violation(Category::FILE)], [], 4, new ExplicitnessMinimum('75'));
        self::assertTrue($met->isMinimumMet());
        self::assertSame(0, $met->getExitCode());
        self::assertSame(['minor' => 0, 'serious' => 0, 'critical' => 1], $met->getSeverityCounts());

        $missed = self::summary([self::violation(Category::FILE)], [], 4, new ExplicitnessMinimum('75.1'));
        self::assertFalse($missed->isMinimumMet());
        self::assertSame(3, $missed->getExitCode());
    }

    public function testParseErrorsSetTheExitCodeEvenWhenTheMinimumIsMet(): void
    {
        $summary = self::summary([self::violation(Category::FILE)], [self::unparseable()], 4, new ExplicitnessMinimum('50'));

        self::assertTrue($summary->isMinimumMet());
        self::assertSame(2, $summary->getExitCode());
    }

    /**
     * Pins the current policy (#114): only an unparseable file raises the exit
     * code; unreadable files and directories leave it, and the percentage,
     * alone.
     */
    public function testOnlyUnparseableInputsRaiseTheExitCode(): void
    {
        $unreadable = [
            new UncheckedInput('locked.php', UncheckedInput::UNREADABLE_FILE),
            new UncheckedInput('locked', UncheckedInput::UNREADABLE_DIRECTORY),
        ];

        $gateMet = self::summary([], $unreadable, 1, new ExplicitnessMinimum('100'));
        self::assertSame(0, $gateMet->getExitCode());
        self::assertSame(1000, $gateMet->getExplicitnessTenths());
        self::assertSame(0, (self::summary([], $unreadable, 1, null))->getExitCode());

        $unparseable = self::summary([], [...$unreadable, self::unparseable()], 1, new ExplicitnessMinimum('100'));
        self::assertTrue($unparseable->isMinimumMet());
        self::assertSame(2, $unparseable->getExitCode());
    }

    public function testWithoutAMinimumThereIsNoThresholdToMiss(): void
    {
        $summary = self::summary([self::violation(Category::STANDARD_OUTPUT)], [], 1, null);

        self::assertNull($summary->getMinimum());
        self::assertTrue($summary->isMinimumMet());
        self::assertSame(1, $summary->getExitCode());
    }

    /**
     * Rounded down, so 2 of 3 is 66.6% and a run can't round up to a
     * minimum it missed; checking nothing is 100%.
     */
    public function testExplicitnessIsInTenthsOfAPercentRoundedDown(): void
    {
        $violation = self::violation(Category::STANDARD_OUTPUT);

        self::assertSame(666, (self::summary([$violation], [], 3, null))->getExplicitnessTenths());
        self::assertSame(998, (self::summary([$violation], [], 501, null))->getExplicitnessTenths());
        self::assertSame(0, (self::summary([$violation], [], 1, null))->getExplicitnessTenths());
        self::assertSame(1000, (self::summary([], [], 7, null))->getExplicitnessTenths());
        self::assertSame(1000, (self::summary([], [self::unparseable()], 0, null))->getExplicitnessTenths());
    }

    /**
     * @param list<Violation> $violations
     * @param list<UncheckedInput> $unchecked
     */
    protected static function summary(array $violations, array $unchecked, int $checked, ?ExplicitnessMinimum $minimum): RunSummary
    {
        $functions = array_map(static fn (Violation $violation): FunctionResult => new FunctionResult(
            $violation->getFunction(), $violation->getLine(), new FunctionAnalysis($violation->getFindings(), [], []),
        ), $violations);
        $functions = array_pad($functions, $checked, new FunctionResult('pure', 1, new FunctionAnalysis([], [], [])));
        $results = [new FileCheckResult('file.php', $functions)];
        $directories = [];
        foreach ($unchecked as $input) {
            if ($input->getReason() === UncheckedInput::UNREADABLE_DIRECTORY) {
                $directories[] = $input;
                continue;
            }
            $results[] = new FileCheckResult('unchecked.php', [], $input);
        }

        return RunSummary::of(new DiscoveredFiles([], $directories), $results, $minimum);
    }

    protected static function unparseable(): UncheckedInput
    {
        return new UncheckedInput('broken.php', UncheckedInput::UNPARSEABLE_FILE, 'Syntax error');
    }

    protected static function violation(string $category): Violation
    {
        return new Violation('file.php', 1, 'fn', [new Finding('a finding', $category, 1)]);
    }
}
