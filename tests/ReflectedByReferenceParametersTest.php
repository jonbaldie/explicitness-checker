<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Tests\Support\CountingByReferenceParameters;
use JonBaldie\ExplicitnessChecker\Tests\Support\ConstructedByReferenceParameters;
use JonBaldie\ExplicitnessChecker\Walk\ReflectedByReferenceParameters;
use PHPUnit\Framework\TestCase;
use JonBaldie\ExplicitnessChecker\SourceChecker;
use JonBaldie\ExplicitnessChecker\Mode;

/**
 * #80: which arguments a built-in takes by reference comes from reflecting on
 * the running PHP, once per function rather than once per call.
 */
class ReflectedByReferenceParametersTest extends TestCase
{
    /**
     * Only functions the running PHP defines internally are known: not
     * user-defined functions, even when loaded, and not undefined ones.
     * Function names are case-insensitive.
     */
    public function testKnowsOnlyBuiltins(): void
    {
        require_once __DIR__ . '/Support/by-reference-function.php';
        $parameters = new ReflectedByReferenceParameters();

        self::assertTrue($parameters->isBuiltin('sort'));
        self::assertTrue($parameters->isBuiltin('SORT'));
        self::assertTrue($parameters->isBuiltin('strlen'));
        self::assertFalse($parameters->isBuiltin('no_such_function'));
        self::assertFalse($parameters->isBuiltin('JonBaldie\ExplicitnessChecker\Tests\Support\take_by_reference'));
    }

    /**
     * A positional argument is by reference when the parameter at its position
     * is, or when it falls on or past a by-reference variadic parameter.
     */
    public function testPositionalArguments(): void
    {
        $parameters = new ReflectedByReferenceParameters();

        self::assertTrue($parameters->isPassedByReference('sort', 0, null));
        self::assertFalse($parameters->isPassedByReference('sort', 1, null));
        self::assertFalse($parameters->isPassedByReference('sort', 2, null));
        self::assertTrue($parameters->isPassedByReference('Preg_Match', 2, null));
        self::assertFalse($parameters->isPassedByReference('preg_match', 1, null));
        self::assertFalse($parameters->isPassedByReference('sscanf', 1, null));
        self::assertTrue($parameters->isPassedByReference('sscanf', 2, null));
        self::assertTrue($parameters->isPassedByReference('sscanf', 7, null));
        self::assertTrue($parameters->isPassedByReference('array_multisort', 0, null));
        self::assertFalse($parameters->isPassedByReference('strlen', 0, null));
    }

    /**
     * A named argument is by reference when the parameter of that name is,
     * wherever it appears in the call.
     */
    public function testNamedArguments(): void
    {
        $parameters = new ReflectedByReferenceParameters();

        self::assertTrue($parameters->isPassedByReference('preg_match', 0, 'matches'));
        self::assertFalse($parameters->isPassedByReference('preg_match', 2, 'subject'));
        self::assertFalse($parameters->isPassedByReference('sort', 0, 'flags'));
        self::assertFalse($parameters->isPassedByReference('sort', 0, 'no_such_parameter'));
    }

    /**
     * Nothing about a function that isn't a built-in is by reference.
     */
    public function testNothingIsByReferenceForUnknownFunctions(): void
    {
        require_once __DIR__ . '/Support/by-reference-function.php';
        $parameters = new ReflectedByReferenceParameters();

        self::assertFalse($parameters->isPassedByReference('no_such_function', 0, null));
        self::assertFalse(
            $parameters->isPassedByReference('JonBaldie\ExplicitnessChecker\Tests\Support\take_by_reference', 0, null),
        );
    }

    /**
     * Asking again gives the same answers, whatever was asked in between.
     */
    public function testRepeatedLookupsAgree(): void
    {
        $parameters = new ReflectedByReferenceParameters();

        self::assertTrue($parameters->isPassedByReference('sort', 0, null));
        self::assertFalse($parameters->isBuiltin('no_such_function'));
        self::assertTrue($parameters->isPassedByReference('SORT', 0, null));
        self::assertFalse($parameters->isPassedByReference('sort', 1, null));
        self::assertFalse($parameters->isBuiltin('no_such_function'));
    }

    /**
     * Each function is reflected once, whatever the spelling of its name and
     * however often it's asked about, and so is each name that isn't a
     * built-in.
     */
    public function testReflectsEachFunctionOnce(): void
    {
        $parameters = new CountingByReferenceParameters();

        $parameters = $parameters->withFunction('sort');
        $parameters->isBuiltin('sort');
        $parameters->isPassedByReference('sort', 0, null);
        $parameters->isPassedByReference('SORT', 1, null);
        $parameters->isPassedByReference('Sort', 0, 'array');
        $parameters = $parameters->withFunction('no_such_function');
        $parameters->isBuiltin('no_such_function');
        $parameters->isPassedByReference('no_such_function', 0, null);
        $parameters = $parameters->withFunction('preg_match');
        $parameters->isPassedByReference('preg_match', 2, null);

        self::assertSame(['sort' => 1, 'no_such_function' => 1, 'preg_match' => 1], $parameters->reflections);
    }
    public function testSnapshotsRemainIsolatedWhenHistoryBranches(): void
    {
        $initial = new CountingByReferenceParameters();
        $first = $initial->withFunction('SoRt');
        $second = $first->withFunction('unknown_snapshot_function');
        $branch = $first->withFunction('preg_match');

        self::assertTrue($second->isPassedByReference('SORT', 0, null));
        self::assertFalse($second->isBuiltin('UNKNOWN_SNAPSHOT_FUNCTION'));
        self::assertTrue($branch->isPassedByReference('PREG_MATCH', 2, null));
        self::assertSame(['SoRt' => 1, 'unknown_snapshot_function' => 1], $second->reflections);
        self::assertSame(['SoRt' => 1, 'preg_match' => 1], $branch->reflections);
        self::assertSame(['SoRt' => 1], $first->reflections);
        self::assertSame([], $initial->reflections);

        self::assertFalse($first->isBuiltin('unknown_snapshot_function'));
        self::assertSame(['SoRt' => 1, 'unknown_snapshot_function' => 1], $first->reflections);
        self::assertTrue($initial->isBuiltin('sort'));
        self::assertSame(['sort' => 1], $initial->reflections);
    }

    public function testSubclassConstructorDoesNotNeedParentInitialization(): void
    {
        $parameters = new ConstructedByReferenceParameters('example');
        self::assertTrue($parameters->isBuiltin('sort'));
        $cached = $parameters->withFunction('sort');
        self::assertTrue($cached->isPassedByReference('SORT', 0, null));
        self::assertSame('example', $cached->label);
    }

    public function testSiblingSnapshotsCanCacheTheSameName(): void
    {
        $initial = new CountingByReferenceParameters();
        $left = $initial->withFunction('sort')->withFunction('preg_match');
        $right = $initial->withFunction('strlen')->withFunction('PREG_MATCH');
        self::assertTrue($left->isPassedByReference('preg_match', 2, null));
        self::assertTrue($right->isPassedByReference('preg_match', 2, null));
        self::assertSame(['sort' => 1, 'preg_match' => 1], $left->reflections);
        self::assertSame(['strlen' => 1, 'PREG_MATCH' => 1], $right->reflections);
    }

    public function testDiscardedDescendantReleasesItsCache(): void
    {
        $first = (new ReflectedByReferenceParameters())->withFunction('sort');
        $descendant = $first->withFunction('preg_match');
        $property = new \ReflectionProperty(ReflectedByReferenceParameters::class, 'cache');
        $property->setAccessible(true);
        $reference = \WeakReference::create($property->getValue($descendant));
        unset($descendant);

        self::assertNull($reference->get());
        self::assertTrue($first->isPassedByReference('sort', 0, null));
    }

    public function testManyUnknownNamesRemainCachedAcrossBranches(): void
    {
        $parameters = new CountingByReferenceParameters();
        for ($i = 0; $i < 128; $i++) {
            $parameters = $parameters->withFunction('unknown_' . $i);
        }
        $branch = $parameters->withFunction('sort');
        for ($i = 0; $i < 128; $i++) {
            self::assertFalse($branch->isBuiltin('UNKNOWN_' . $i));
            self::assertSame(1, $branch->reflections['unknown_' . $i]);
        }
        self::assertTrue($branch->isPassedByReference('sort', 0, null));
        self::assertSame(1, $branch->reflections['sort']);
    }

    public function testNumericStringCallableCanPrecedeAnotherFunction(): void
    {
        $checker = new SourceChecker();
        [$results, $cache] = $checker->checkWithCache(
            '<?php function demo(&$x) { ("123")(); sort($x); }',
            new Mode(false, false),
        );

        self::assertCount(1, $results);
        self::assertFalse($cache->isBuiltin('123'));
        self::assertTrue($cache->isPassedByReference('sort', 0, null));
    }

    public function testSourcePassesReuseExplicitSignatureSnapshots(): void
    {
        $initial = new CountingByReferenceParameters();
        $checker = new SourceChecker($initial);
        [, $first] = $checker->checkWithCache('<?php function a($x) { sort($x); unknown_cache_call($x); } function b($x) { SORT($x); }', new Mode(false, false));
        self::assertInstanceOf(CountingByReferenceParameters::class, $first);
        self::assertSame([], $initial->reflections);
        self::assertSame(['sort' => 1, 'unknown_cache_call' => 1], $first->reflections);
        [, $second] = $checker->withParameters($first)->checkWithCache('<?php function c($x) { sort($x); UNKNOWN_CACHE_CALL($x); preg_match("a", "b", $m); }', new Mode(false, false));
        self::assertInstanceOf(CountingByReferenceParameters::class, $second);
        self::assertSame(['sort' => 1, 'unknown_cache_call' => 1], $first->reflections);
        self::assertSame(['sort' => 1, 'unknown_cache_call' => 1, 'preg_match' => 1], $second->reflections);
    }

}
