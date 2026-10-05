<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

use RecursiveArrayIterator;
use RecursiveIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;

/**
 * Continues a recursive walk when a child directory cannot be opened, and
 * records each one it skips.
 *
 * @extends RecursiveIteratorIterator<RecursiveIterator<mixed, mixed>>
 */
class SafeRecursiveIteratorIterator extends RecursiveIteratorIterator
{
    /** @var list<UncheckedInput> */
    protected array $unchecked = [];

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
            $this->unchecked[] = new UncheckedInput($path, UncheckedInput::UNREADABLE_DIRECTORY);

            return new RecursiveArrayIterator([]);
        }
    }

    /**
     * The child directories skipped so far, in walk order.
     *
     * @return list<UncheckedInput>
     */
    public function getUnchecked(): array
    {
        return $this->unchecked;
    }
}
