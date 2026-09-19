<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\Finding;

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

    /** Severity of each category's findings. */
    protected const CATEGORIES = [
        Category::STANDARD_OUTPUT => self::MINOR,
        Category::GLOBAL_VARIABLE => self::SERIOUS,
        Category::SUPERGLOBAL => self::SERIOUS,
        Category::GLOBALS_ARRAY => self::SERIOUS,
        Category::OBJECT_PROPERTY => self::SERIOUS,
        Category::STATIC_PROPERTY => self::SERIOUS,
        Category::FILE => self::CRITICAL,
        Category::FILE_SYSTEM => self::CRITICAL,
        Category::ENVIRONMENT => self::CRITICAL,
        Category::TIME => self::CRITICAL,
        Category::RANDOM => self::CRITICAL,
        Category::HTTP_HEADERS => self::CRITICAL,
        Category::ERROR_LOG => self::CRITICAL,
        Category::SESSION => self::CRITICAL,
    ];

    /**
     * The highest severity of any finding; minor if there are none.
     *
     * @param list<Finding> $findings
     *
     * @return self::MINOR|self::SERIOUS|self::CRITICAL
     */
    public static function of(array $findings): string
    {
        $severity = self::MINOR;
        foreach ($findings as $finding) {
            $found = self::ofFinding($finding);
            if (self::EXIT_CODES[$found] > self::EXIT_CODES[$severity]) {
                $severity = $found;
            }
        }

        return $severity;
    }

    /**
     * By category, except that `$_ENV` is environment access, so critical
     * although it's reported as a superglobal.
     *
     * @return self::MINOR|self::SERIOUS|self::CRITICAL
     */
    protected static function ofFinding(Finding $finding): string
    {
        if ($finding->getCategory() === Category::SUPERGLOBAL && str_ends_with($finding->getDescription(), ' $_ENV')) {
            return self::CRITICAL;
        }

        return self::CATEGORIES[$finding->getCategory()] ?? self::MINOR;
    }
}
