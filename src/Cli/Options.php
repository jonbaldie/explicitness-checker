<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

/**
 * The parsed command line.
 */
class Options
{
    public function __construct(
        protected string $path,
        protected bool $verbose,
        protected bool $strict,
        protected bool $props,
        protected FileFilter $filter,
    ) {
    }

    /**
     * The file or directory to check, as given.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    public function isVerbose(): bool
    {
        return $this->verbose;
    }

    public function isStrict(): bool
    {
        return $this->strict;
    }

    public function isProps(): bool
    {
        return $this->props;
    }

    public function getFilter(): FileFilter
    {
        return $this->filter;
    }
}
