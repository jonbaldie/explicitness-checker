<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

/**
 * A file or directory the run could not check, and why. RunSummary decides
 * what each reason does to the exit code.
 */
class UncheckedInput
{
    /** A PHP file that could not be read. */
    public const UNREADABLE_FILE = 'unreadable file';
    /** A directory that could not be opened, so nothing under it was found. */
    public const UNREADABLE_DIRECTORY = 'unreadable directory';
    /** A PHP file that was read but did not parse. */
    public const UNPARSEABLE_FILE = 'unparseable file';

    /**
     * @param self::UNREADABLE_FILE|self::UNREADABLE_DIRECTORY|self::UNPARSEABLE_FILE $reason
     * @param string|null                                                             $detail the parser's message for an unparseable file
     */
    public function __construct(
        protected string $path,
        protected string $reason,
        protected ?string $detail = null,
    ) {
    }

    /**
     * The path, as it was given to or found by the run.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return self::UNREADABLE_FILE|self::UNREADABLE_DIRECTORY|self::UNPARSEABLE_FILE
     */
    public function getReason(): string
    {
        return $this->reason;
    }

    /**
     * The parser's message for an unparseable file; null otherwise.
     */
    public function getDetail(): ?string
    {
        return $this->detail;
    }
}
