<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

use RecursiveArrayIterator;
use RecursiveIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;

/**
 * Continues a recursive walk when a child directory cannot be opened.
 *
 * @extends RecursiveIteratorIterator<RecursiveIterator<mixed, mixed>>
 */
class SafeRecursiveIteratorIterator extends RecursiveIteratorIterator
{
    /**
     * @param RecursiveIterator<mixed, mixed> $iterator
     */
    public function __construct(RecursiveIterator $iterator, protected Console $console)
    {
        parent::__construct($iterator);
    }

    /**
     * @return RecursiveIterator<mixed, mixed>|null
     */
    public function callGetChildren(): ?RecursiveIterator
    {
        try {
            return parent::callGetChildren();
        } catch (UnexpectedValueException) {
            $current = $this->current();
            $path = $current instanceof SplFileInfo ? $current->getPathname() : '<unknown>';
            $this->console->error("Cannot read directory: {$path}" . PHP_EOL);

            return new RecursiveArrayIterator([]);
        }
    }
}
