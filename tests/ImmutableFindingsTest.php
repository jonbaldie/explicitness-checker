<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\FindingCollector;
use PhpParser\Node\Expr;
use PHPUnit\Framework\TestCase;

class ImmutableFindingsTest extends TestCase
{
    public function testRecordingReturnsANewSnapshotAndKeepsFirstOccurrence(): void
    {
        $before = new FindingCollector();
        $after = $before->input('input', Category::GLOBAL_VARIABLE, new Expr\Variable('x', ['startLine' => 3]));

        self::assertInstanceOf(FindingCollector::class, $after);
        self::assertSame([], $before->findings());
        self::assertCount(1, $after->findings());
        $again = $after->input('input', Category::GLOBAL_VARIABLE, new Expr\Variable('x', ['startLine' => 9]));
        self::assertSame(3, $again->findings()[0]->getLine());
        $output = $again->output('output', Category::GLOBAL_VARIABLE, new Expr\Variable('x', ['startLine' => 10]));
        self::assertCount(1, $again->findings());
        self::assertCount(2, $output->findings());
        self::assertTrue($output->findings()[1]->isOutput());
    }
}
