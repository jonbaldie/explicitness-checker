<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker;

/**
 * The names of PHP's superglobals, without the "$".
 *
 * Bare `$GLOBALS` is one of them. A `$GLOBALS[...]` fetch is not a bare
 * superglobal; GlobalsArray owns that.
 */
class Superglobals
{
    protected const NAMES = [
        '_COOKIE' => true,
        '_ENV' => true,
        '_FILES' => true,
        '_GET' => true,
        '_POST' => true,
        '_REQUEST' => true,
        '_SERVER' => true,
        '_SESSION' => true,
        GlobalsArray::NAME => true,
    ];

    public static function includes(string $name): bool
    {
        return isset(self::NAMES[$name]);
    }
}
