<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

/**
 * PHP's parsed fields show whether a literal supplies its own calendar date.
 * Ordinary date parsing supplies midnight for an otherwise complete date;
 * relative adjustments to that fixed anchor do not introduce a clock read.
 */
final class DateLiteral
{
    public static function readsClock(string $datetime): bool
    {
        if (trim($datetime) === '') {
            return true;
        }
        $parsed = date_parse($datetime);
        if ($parsed['error_count'] !== 0) {
            return false;
        }
        foreach (['year', 'month', 'day'] as $field) {
            if ($parsed[$field] === false) {
                return true;
            }
        }

        return false;
    }
}
