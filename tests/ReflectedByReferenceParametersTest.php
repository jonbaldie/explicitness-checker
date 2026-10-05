<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Tests\Support\CountingByReferenceParameters;
use JonBaldie\ExplicitnessChecker\Walk\ReflectedByReferenceParameters;
use PHPUnit\Framework\TestCase;

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

        $parameters->isBuiltin('sort');
        $parameters->isPassedByReference('sort', 0, null);
        $parameters->isPassedByReference('SORT', 1, null);
        $parameters->isPassedByReference('Sort', 0, 'array');
        $parameters->isBuiltin('no_such_function');
        $parameters->isPassedByReference('no_such_function', 0, null);
        $parameters->isPassedByReference('preg_match', 2, null);

        self::assertSame(['sort' => 1, 'no_such_function' => 1, 'preg_match' => 1], $parameters->reflections);
    }
}
