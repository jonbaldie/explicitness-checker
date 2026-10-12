<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use DateTimeImmutable;
use ValueError;

/**
 * The format parser leaves false fields where it would use the clock.
 * When input is not a literal, a fixed UTC sample exercises the format's
 * directives without evaluating the application's expression.
 */
final class DateFormat
{
    public static function readsClock(string $format, ?string $datetime): bool
    {
        $datetime ??= (new DateTimeImmutable('2000-02-03T04:05:06+00:00'))->format(self::sampleFormat($format));
        try {
            $parsed = date_parse_from_format($format, $datetime);
        } catch (ValueError) {
            return false;
        }
        if ($parsed['error_count'] !== 0) {
            return false;
        }
        foreach (['year', 'month', 'day', 'hour', 'minute', 'second'] as $field) {
            if ($parsed[$field] === false) {
                return true;
            }
        }

        return false;
    }

    /** Remove input-free directives, preserving escaped literal characters. */
    protected static function sampleFormat(string $format): string
    {
        $sample = '';
        $length = strlen($format);
        for ($index = 0; $index < $length; $index++) {
            $character = $format[$index];
            if ($character === '\\' && $index + 1 < $length) {
                $sample .= $character . $format[++$index];
                continue;
            }
            if (!in_array($character, ['!', '|', '+'], true)) {
                $sample .= $character;
            }
        }

        return $sample;
    }
}
