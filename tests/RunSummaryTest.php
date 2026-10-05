<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\Cli\ExplicitnessMinimum;
use JonBaldie\ExplicitnessChecker\Cli\RunSummary;
use JonBaldie\ExplicitnessChecker\Cli\Violation;
use JonBaldie\ExplicitnessChecker\Finding;
use PHPUnit\Framework\TestCase;

/**
 * The outcome of a run, without a Console (#77): what was found, how explicit
 * the checked function-likes were, and the exit code.
 */
class RunSummaryTest extends TestCase
{
    public function testACleanRunExitsZero(): void
    {
        $summary = new RunSummary([], false, 4, null);

        self::assertSame(['minor' => 0, 'serious' => 0, 'critical' => 0], $summary->getSeverityCounts());
        self::assertSame(4, $summary->getCheckedCount());
        self::assertSame(4, $summary->getExplicitCount());
        self::assertSame(0, $summary->getExitCode());
    }

    public function testViolationsExitWithTheHighestSeverityFound(): void
    {
        $summary = new RunSummary(
            [self::violation(Category::STANDARD_OUTPUT), self::violation(Category::GLOBAL_VARIABLE), self::violation(Category::STANDARD_OUTPUT)],
            false,
            5,
            null,
        );

        self::assertSame(['minor' => 2, 'serious' => 1, 'critical' => 0], $summary->getSeverityCounts());
        self::assertSame(2, $summary->getExplicitCount());
        self::assertSame(2, $summary->getExitCode());
        self::assertSame(1, (new RunSummary([self::violation(Category::STANDARD_OUTPUT)], false, 1, null))->getExitCode());
        self::assertSame(3, (new RunSummary([self::violation(Category::FILE), self::violation(Category::GLOBAL_VARIABLE)], false, 2, null))->getExitCode());
    }

    public function testParseErrorsExitAtLeastSerious(): void
    {
        self::assertSame(2, (new RunSummary([], true, 0, null))->getExitCode());
        self::assertSame(2, (new RunSummary([self::violation(Category::STANDARD_OUTPUT)], true, 1, null))->getExitCode());
        self::assertSame(3, (new RunSummary([self::violation(Category::FILE)], true, 1, null))->getExitCode());
    }

    /**
     * 3 of 4 checked function-likes are explicit: 75%.
     */
    public function testMeetingTheMinimumStopsViolationsSettingTheExitCode(): void
    {
        $met = new RunSummary([self::violation(Category::FILE)], false, 4, new ExplicitnessMinimum('75'));
        self::assertTrue($met->isMinimumMet());
        self::assertSame(0, $met->getExitCode());
        self::assertSame(['minor' => 0, 'serious' => 0, 'critical' => 1], $met->getSeverityCounts());

        $missed = new RunSummary([self::violation(Category::FILE)], false, 4, new ExplicitnessMinimum('75.1'));
        self::assertFalse($missed->isMinimumMet());
        self::assertSame(3, $missed->getExitCode());
    }

    public function testParseErrorsSetTheExitCodeEvenWhenTheMinimumIsMet(): void
    {
        $summary = new RunSummary([self::violation(Category::FILE)], true, 4, new ExplicitnessMinimum('50'));

        self::assertTrue($summary->isMinimumMet());
        self::assertSame(2, $summary->getExitCode());
    }

    public function testWithoutAMinimumThereIsNoThresholdToMiss(): void
    {
        $summary = new RunSummary([self::violation(Category::STANDARD_OUTPUT)], false, 1, null);

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

        self::assertSame(666, (new RunSummary([$violation], false, 3, null))->getExplicitnessTenths());
        self::assertSame(998, (new RunSummary([$violation], false, 501, null))->getExplicitnessTenths());
        self::assertSame(0, (new RunSummary([$violation], false, 1, null))->getExplicitnessTenths());
        self::assertSame(1000, (new RunSummary([], false, 7, null))->getExplicitnessTenths());
        self::assertSame(1000, (new RunSummary([], true, 0, null))->getExplicitnessTenths());
    }

    protected static function violation(string $category): Violation
    {
        return new Violation('file.php', 1, 'fn', [new Finding('a finding', $category, 1)], []);
    }
}
