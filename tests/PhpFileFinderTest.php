<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Cli\Console;
use JonBaldie\ExplicitnessChecker\Cli\DiscoveredFiles;
use JonBaldie\ExplicitnessChecker\Cli\FileFilter;
use JonBaldie\ExplicitnessChecker\Cli\PhpFileFinder;
use JonBaldie\ExplicitnessChecker\Cli\UncheckedInput;
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

        $found = $finder->find(Process::ROOT . '/tests/Fixtures/cli');
        self::assertSame([$readable], $found->getFiles());
        self::assertSame([[$unreadable, UncheckedInput::UNREADABLE_DIRECTORY]], self::unchecked($found));

        rewind($err);
        self::assertSame(
            "Cannot read directory: {$unreadable}\n",
            (string) stream_get_contents($err),
        );
    }

    /**
     * #114: a directory the walk cannot open reaches the caller as data, at
     * the top level or found during recursion, not only on stderr.
     */
    public function testUnreadableDirectoriesAreReportedToTheCaller(): void
    {
        $dir = sys_get_temp_dir() . '/ec_unreadable_dir_test_' . uniqid();
        $locked = $dir . '/locked';
        mkdir($locked, 0777, true);
        file_put_contents($dir . '/clean.php', "<?php\n");
        file_put_contents($locked . '/hidden.php', "<?php\n");
        chmod($locked, 0000);

        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $finder = new PhpFileFinder(new FileFilter([], [], []), new Console($stream, $stream, false));

        try {
            $nested = $finder->find($dir);
            self::assertSame([$dir . '/clean.php'], $nested->getFiles());
            self::assertSame([[$locked, UncheckedInput::UNREADABLE_DIRECTORY]], self::unchecked($nested));

            $topLevel = $finder->find($locked);
            self::assertSame([], $topLevel->getFiles());
            self::assertSame([[$locked, UncheckedInput::UNREADABLE_DIRECTORY]], self::unchecked($topLevel));

            rewind($stream);
            self::assertSame(
                "Cannot read directory: {$locked}\nCannot read directory: {$locked}\n",
                (string) stream_get_contents($stream),
            );
        } finally {
            chmod($locked, 0755);
            unlink($locked . '/hidden.php');
            rmdir($locked);
            unlink($dir . '/clean.php');
            rmdir($dir);
        }
    }

    public function testReadableTreesHaveNoUncheckedDirectories(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $finder = new PhpFileFinder(new FileFilter([], [], []), new Console($stream, $stream, false));
        $file = Process::ROOT . '/tests/Fixtures/cli/minor-only.php';

        self::assertSame([], $finder->find($file)->getUnchecked());
        self::assertSame([], $finder->find(Process::ROOT . '/tests/Fixtures/cli')->getUnchecked());
    }

    /**
     * @return list<array{string, string}>
     */
    protected static function unchecked(DiscoveredFiles $found): array
    {
        return array_map(
            static fn (UncheckedInput $input): array => [$input->getPath(), $input->getReason()],
            $found->getUnchecked(),
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
