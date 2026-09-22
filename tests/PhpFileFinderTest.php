<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Cli\Console;
use JonBaldie\ExplicitnessChecker\Cli\FileFilter;
use JonBaldie\ExplicitnessChecker\Cli\PhpFileFinder;
use JonBaldie\ExplicitnessChecker\Tests\Support\Process;
use PHPUnit\Framework\TestCase;
use RecursiveIterator;
use SplFileInfo;
use UnexpectedValueException;

class PhpFileFinderTest extends TestCase
{
    public function testUnreadableChildDirectoryDoesNotStopTheWalk(): void
    {
        $out = fopen('php://memory', 'w+');
        $err = fopen('php://memory', 'w+');
        self::assertIsResource($out);
        self::assertIsResource($err);

        $readable = Process::ROOT . '/tests/Fixtures/cli/minor-only.php';
        $unreadable = Process::ROOT . '/tests/Fixtures/cli/unreadable';
        $finder = new SimulatedUnreadableDirectoryFinder(
            new FileFilter([], [], []),
            new Console($out, $err, false),
            new SimulatedDirectoryIterator(
                [new SplFileInfo($readable), new SplFileInfo($unreadable)],
                $unreadable,
            ),
        );

        self::assertSame([$readable], $finder->find(Process::ROOT . '/tests/Fixtures/cli'));

        rewind($err);
        self::assertSame(
            "Cannot read directory: {$unreadable}\n",
            (string) stream_get_contents($err),
        );
    }
}

class SimulatedUnreadableDirectoryFinder extends PhpFileFinder
{
    public function __construct(
        FileFilter $filter,
        Console $console,
        protected RecursiveIterator $iterator,
    ) {
        parent::__construct($filter, $console);
    }

    protected function createDirectoryIterator(string $path): RecursiveIterator
    {
        return $this->iterator;
    }
}

class SimulatedDirectoryIterator implements RecursiveIterator
{
    protected int $position = 0;

    /**
     * @param list<SplFileInfo> $entries
     */
    public function __construct(protected array $entries, protected string $unreadable)
    {
    }

    public function current(): mixed
    {
        return $this->entries[$this->position] ?? null;
    }

    public function key(): mixed
    {
        return $this->position;
    }

    public function next(): void
    {
        $this->position++;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return isset($this->entries[$this->position]);
    }

    public function hasChildren(): bool
    {
        $current = $this->current();

        return $current instanceof SplFileInfo && $current->getPathname() === $this->unreadable;
    }

    public function getChildren(): ?RecursiveIterator
    {
        throw new UnexpectedValueException('simulated unreadable directory');
    }
}
