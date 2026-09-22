<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

use JonBaldie\ExplicitnessChecker\Mode;

/**
 * The parsed command line.
 */
class Options
{
    public function __construct(
        protected string $path,
        protected bool $verbose,
        protected Mode $mode,
        protected FileFilter $filter,
        protected ?ExplicitnessMinimum $minimum,
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

    public function getMode(): Mode
    {
        return $this->mode;
    }

    public function getFilter(): FileFilter
    {
        return $this->filter;
    }

    /**
     * The --min-explicitness threshold, or null when it wasn't given.
     */
    public function getMinimum(): ?ExplicitnessMinimum
    {
        return $this->minimum;
    }
}
