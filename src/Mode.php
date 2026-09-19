<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker;

/**
 * Which optional checks are on: the CLI's --strict and --props flags, or the
 * PHPStan extension's `explicitness.strict` and `explicitness.props` parameters.
 */
class Mode
{
    /**
     * @param bool $strict also report output functions, file, time, random,
     *                     environment, header, error-log and session access
     * @param bool $props  also report `$this->prop` and `Class::$prop` access
     */
    public function __construct(
        protected bool $strict,
        protected bool $props,
    ) {
    }

    public function isStrict(): bool
    {
        return $this->strict;
    }

    public function isProps(): bool
    {
        return $this->props;
    }
}
