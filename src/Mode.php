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
     * @param bool $strict also report built-in I/O: output, file, file system,
     *                     environment, time, random, headers, error log,
     *                     session, network, database, process, mail, include
     *                     and runtime config
     * @param bool $props  also report `$this->prop` access
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
