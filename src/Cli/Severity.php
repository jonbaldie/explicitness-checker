<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

/**
 * How bad a reported function-like is, which sets the CLI's exit code.
 */
class Severity
{
    /** Exit code 1: echo, print, etc. */
    public const MINOR = 'minor';
    /** Exit code 2: globals, superglobals, properties. */
    public const SERIOUS = 'serious';
    /** Exit code 3: file I/O, time, random, etc. */
    public const CRITICAL = 'critical';

    /** Exit code for each severity, highest last. */
    public const EXIT_CODES = [
        self::MINOR => 1,
        self::SERIOUS => 2,
        self::CRITICAL => 3,
    ];

    /** Substrings of a finding that make it critical. */
    protected const CRITICAL_MARKERS = [
        'reads from file',
        'writes to file',
        'reads system time',
        'reads from random number generator',
        'writes to random number generator state',
        'reads from environment variables',
        'writes to environment variables',
        'superglobal $_ENV',
        'reads from file system',
        'writes HTTP headers',
        'writes to error log',
        'reads session state',
        'writes to session state',
    ];

    /** Substrings of a finding that make it serious. */
    protected const SERIOUS_MARKERS = [
        'global variable',
        '$GLOBALS',
        'superglobal',
        'object property',
        'static property',
    ];

    /**
     * Critical wins over serious; everything else is minor (output functions).
     *
     * @param list<string> $descriptions finding descriptions
     *
     * @return self::MINOR|self::SERIOUS|self::CRITICAL
     */
    public static function of(array $descriptions): string
    {
        if (self::anyContains($descriptions, self::CRITICAL_MARKERS)) {
            return self::CRITICAL;
        }
        if (self::anyContains($descriptions, self::SERIOUS_MARKERS)) {
            return self::SERIOUS;
        }

        return self::MINOR;
    }

    /**
     * @param list<string> $descriptions
     * @param list<string> $markers
     */
    protected static function anyContains(array $descriptions, array $markers): bool
    {
        foreach ($descriptions as $description) {
            foreach ($markers as $marker) {
                if (str_contains($description, $marker)) {
                    return true;
                }
            }
        }

        return false;
    }
}
