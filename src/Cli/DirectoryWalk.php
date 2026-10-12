<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

use Generator;
use RecursiveIterator;
use SplFileInfo;
use UnexpectedValueException;

/** Streams leaf entries and unreadable-directory outcomes in traversal order. */
class DirectoryWalk
{
    /** @param RecursiveIterator<mixed, mixed> $iterator
     *  @return Generator<mixed, mixed, void, void>
     */
    public static function events(RecursiveIterator $iterator): Generator
    {
        foreach ($iterator as $key => $entry) {
            if (!$iterator->hasChildren()) {
                yield $key => $entry;
                continue;
            }
            try {
                $children = self::childrenOf($iterator);
            } catch (UnexpectedValueException) {
                $path = $entry instanceof SplFileInfo ? $entry->getPathname() : '<unknown>';
                yield $key => new UncheckedInput($path, UncheckedInput::UNREADABLE_DIRECTORY);
                continue;
            }
            if ($children !== null) {
                yield from self::events($children);
            }
        }
    }
    /** The native RecursiveIterator contract permits a null child iterator.
     *  @param RecursiveIterator<mixed, mixed> $iterator
     *  @return RecursiveIterator<mixed, mixed>|null
     */
    protected static function childrenOf(RecursiveIterator $iterator): ?RecursiveIterator
    {
        return $iterator->getChildren();
    }

}
