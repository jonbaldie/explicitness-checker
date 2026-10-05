<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

/**
 * What finding the PHP files under a path produced: the files to check, and
 * the directories that could not be opened.
 */
class DiscoveredFiles
{
    /**
     * @param list<string>         $files     file paths, sorted
     * @param list<UncheckedInput> $unchecked the directories that could not be opened, in walk order
     */
    public function __construct(protected array $files, protected array $unchecked = [])
    {
    }

    /**
     * @return list<string> file paths, sorted
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * @return list<UncheckedInput> the directories that could not be opened, in walk order
     */
    public function getUnchecked(): array
    {
        return $this->unchecked;
    }
}
