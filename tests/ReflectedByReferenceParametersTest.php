<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

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

        self::assertTrue($parameters->isByReference('sort', 0, null));
        self::assertFalse($parameters->isByReference('sort', 1, null));
        self::assertFalse($parameters->isByReference('sort', 2, null));
        self::assertTrue($parameters->isByReference('Preg_Match', 2, null));
        self::assertFalse($parameters->isByReference('preg_match', 1, null));
        self::assertFalse($parameters->isByReference('sscanf', 1, null));
        self::assertTrue($parameters->isByReference('sscanf', 2, null));
        self::assertTrue($parameters->isByReference('sscanf', 7, null));
        self::assertTrue($parameters->isByReference('array_multisort', 0, null));
        self::assertFalse($parameters->isByReference('strlen', 0, null));
    }

    /**
     * A named argument is by reference when the parameter of that name is,
     * wherever it appears in the call.
     */
    public function testNamedArguments(): void
    {
        $parameters = new ReflectedByReferenceParameters();

        self::assertTrue($parameters->isByReference('preg_match', 0, 'matches'));
        self::assertFalse($parameters->isByReference('preg_match', 2, 'subject'));
        self::assertFalse($parameters->isByReference('sort', 0, 'flags'));
        self::assertFalse($parameters->isByReference('sort', 0, 'no_such_parameter'));
    }

    /**
     * Nothing about a function that isn't a built-in is by reference.
     */
    public function testNothingIsByReferenceForUnknownFunctions(): void
    {
        require_once __DIR__ . '/Support/by-reference-function.php';
        $parameters = new ReflectedByReferenceParameters();

        self::assertFalse($parameters->isByReference('no_such_function', 0, null));
        self::assertFalse(
            $parameters->isByReference('JonBaldie\ExplicitnessChecker\Tests\Support\take_by_reference', 0, null),
        );
    }

    /**
     * Asking again gives the same answers, whatever was asked in between.
     */
    public function testRepeatedLookupsAgree(): void
    {
        $parameters = new ReflectedByReferenceParameters();

        self::assertTrue($parameters->isByReference('sort', 0, null));
        self::assertFalse($parameters->isBuiltin('no_such_function'));
        self::assertTrue($parameters->isByReference('SORT', 0, null));
        self::assertFalse($parameters->isByReference('sort', 1, null));
        self::assertFalse($parameters->isBuiltin('no_such_function'));
    }
}
