<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Tests\Support\Process;
use PHPUnit\Framework\TestCase;
use JonBaldie\ExplicitnessChecker\Cli\FileChecker;
use JonBaldie\ExplicitnessChecker\SourceChecker;
use JonBaldie\ExplicitnessChecker\Mode;
use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\Project\ProjectAnalysis;

class TransitiveCliTest extends TestCase
{
    public function testPropagatesAnArgumentBearingChainAcrossFiles(): void
    {
        [$exit, $stdout, $stderr] = Process::cli(['--transitive', 'tests/Fixtures/transitive/chain']);

        self::assertSame('', $stderr);
        self::assertSame(2, $exit);
        self::assertStringContainsString('calls action Demo\\b() (line 3): implicit input', $stdout);
        self::assertStringContainsString('calls action Demo\\c() (line 3): implicit input', $stdout);
        self::assertStringNotContainsString('implicit output', $stdout);
        self::assertStringContainsString('read from global variable $shared', $stdout);
    }

    public function testResolvesLexicalAndInheritedMethodCalls(): void
    {
        [$exit, $stdout, $stderr] = Process::cli(['--transitive', 'tests/Fixtures/transitive/methods.php']);

        self::assertSame('', $stderr);
        self::assertSame(2, $exit);
        self::assertStringContainsString('calls action Dispatch\\BaseType::input() (line 4): implicit input', $stdout);
        self::assertStringContainsString('calls action Dispatch\\BaseType::output() (line 5): implicit output', $stdout);
        self::assertStringContainsString('calls action Dispatch\\BaseType::via() (line 6): implicit input', $stdout);
        self::assertStringNotContainsString('ChildType::override ', $stdout);
        self::assertStringNotContainsString('ChildType::lateStatic ', $stdout);
        self::assertStringNotContainsString('ChildType::localSelf ', $stdout);
    }

    public function testPureAndImpureCyclesTerminateWithIndependentDirections(): void
    {
        [$exit, $stdout, $stderr] = Process::cli(['--transitive', 'tests/Fixtures/transitive/cycles.php']);
        self::assertSame('', $stderr);
        self::assertSame(2, $exit);
        self::assertStringNotContainsString('| pureA ', $stdout);
        self::assertStringNotContainsString('| pureB ', $stdout);
        self::assertStringContainsString('calls action right() (line 7): implicit input', $stdout);
        self::assertStringContainsString('calls action right() (line 7): implicit output', $stdout);
        self::assertStringContainsString('calls action left() (line 6): implicit input', $stdout);
        self::assertStringContainsString('calls action left() (line 6): implicit output', $stdout);
    }

    public function testFindingsKeepDirectionAndFirstCallSite(): void
    {
        $file = (new FileChecker(new SourceChecker(), new Mode(false, false), true))->check(Process::ROOT . '/tests/Fixtures/transitive/cycles.php');
        $updated = (new ProjectAnalysis())->propagate([$file])[0];
        $functions = $updated->getFunctions();
        $read = $functions[6]->getFindings();
        $write = $functions[7]->getFindings();
        $repeated = $functions[8]->getFindings();

        self::assertCount(1, $read);
        self::assertSame(Category::CALLS_ACTION, $read[0]->getCategory());
        self::assertTrue($read[0]->isInput());
        self::assertSame(9, $read[0]->getLine());
        self::assertCount(1, $write);
        self::assertTrue($write[0]->isOutput());
        self::assertSame(12, $write[0]->getLine());
        self::assertCount(1, $repeated);
        self::assertSame(15, $repeated[0]->getLine());
    }

    public function testNestedBodiesAndCallableConstructionDoNotSeedTheirParent(): void
    {
        [, $stdout, $stderr] = Process::cli(['--transitive', 'tests/Fixtures/transitive/scopes.php']);
        self::assertSame('', $stderr);
        self::assertStringNotContainsString('Scopes\\outer ', $stdout);
        self::assertStringNotContainsString('Scopes\\callableFactory ', $stdout);
        self::assertStringNotContainsString('Scopes\\invoke ', $stdout);
        self::assertStringNotContainsString('Scopes\\supplies ', $stdout);
        self::assertSame(5, substr_count($stdout, 'calls action Scopes\\action() (line 3): implicit output'));
    }

    public function testImportsFallbackAndNamespacePrecedence(): void
    {
        [, $stdout, $stderr] = Process::cli(['--transitive', 'tests/Fixtures/transitive/names']);
        self::assertSame('', $stderr);
        self::assertStringNotContainsString('Names\\takesLocal ', $stdout);
        self::assertStringNotContainsString('Names\\namespaceRelative ', $stdout);
        self::assertSame(3, substr_count($stdout, 'calls action rootAction() (line 2): implicit input'));
    }

    public function testMissingAndAmbiguousTargetsDoNotCreateEdges(): void
    {
        [, $stdout, $stderr] = Process::cli(['--transitive', 'tests/Fixtures/transitive/ambiguous']);
        self::assertSame('', $stderr);
        self::assertStringNotContainsString('calls action', $stdout);
        self::assertStringNotContainsString('ambiguousCaller ', $stdout);
        self::assertStringNotContainsString('missingCaller ', $stdout);
        self::assertStringNotContainsString('Twice::caller ', $stdout);
    }

    public function testActiveModesControlSeedsAndTheSelectedFilesBoundPropagation(): void
    {
        [, $plain] = Process::cli(['--transitive', 'tests/Fixtures/transitive/modes.php']);
        self::assertStringNotContainsString('calls action', $plain);
        [, $strict] = Process::cli(['--transitive', '--strict', 'tests/Fixtures/transitive/modes.php']);
        self::assertStringContainsString('calls action nowSeed() (line 2): implicit input', $strict);
        [, $props] = Process::cli(['--transitive', '--props', 'tests/Fixtures/transitive/modes.php']);
        self::assertStringContainsString('calls action Box::seed() (line 5): implicit input', $props);
        [, $excluded] = Process::cli(['--transitive', '--exclude-pattern=c\\.php$', 'tests/Fixtures/transitive/chain']);
        self::assertStringNotContainsString('calls action', $excluded);
        self::assertStringContainsString('No implicit inputs or outputs found.', $excluded);
    }

    public function testReachabilityDoesNotDependOnFileOrder(): void
    {
        $checker = new FileChecker(new SourceChecker(), new Mode(false, false), true);
        $files = [];
        foreach (['a', 'b', 'c'] as $name) {
            $files[] = $checker->check(Process::ROOT . '/tests/Fixtures/transitive/chain/' . $name . '.php');
        }
        $project = new ProjectAnalysis();
        $forward = [];
        foreach ($project->propagate($files) as $file) {
            $forward[$file->getFile()] = $file->getFunctions();
        }
        $reverse = [];
        foreach ($project->propagate(array_reverse($files)) as $file) {
            $reverse[$file->getFile()] = $file->getFunctions();
        }
        ksort($forward);
        ksort($reverse);

        self::assertEquals($forward, $reverse);
    }

    public function testUnreadableFilesContributeNoDeclarationsOrCounts(): void
    {
        $checker = new FileChecker(new SourceChecker(), new Mode(false, false), true);
        $unreadable = $checker->check(Process::ROOT . '/tests/Fixtures/transitive/absent.php');
        $caller = $checker->check(Process::ROOT . '/tests/Fixtures/transitive/chain/a.php');
        $updated = (new ProjectAnalysis())->propagate([$unreadable, $caller]);

        self::assertSame($unreadable->getUnchecked(), $updated[0]->getUnchecked());
        self::assertSame(0, $updated[0]->getChecked());
        self::assertSame([], $updated[1]->getFunctions()[0]->getFindings());
    }

}
