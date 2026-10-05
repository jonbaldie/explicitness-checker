<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

use RecursiveDirectoryIterator;
use RecursiveIterator;
use SplFileInfo;
use UnexpectedValueException;

/**
 * Finds the PHP files to check under a path, applying the FileFilter.
 *
 * A path that is itself a file is only filtered by the patterns, not by the
 * excluded directories.
 */
class PhpFileFinder
{
    protected const PHP_FILE = '/\.php$/i';

    public function __construct(protected FileFilter $filter, protected Console $console)
    {
    }

    public function find(string $path): DiscoveredFiles
    {
        if (is_file($path)) {
            return $this->findFile($path);
        }

        return $this->findInDirectory($path);
    }

    protected function findFile(string $path): DiscoveredFiles
    {
        if (!preg_match(self::PHP_FILE, $path)) {
            $this->console->verbose("Path is file but not PHP: {$path}");

            return new DiscoveredFiles([]);
        }

        $this->console->verbose("Path is file and ends with .php: {$path}");

        return new DiscoveredFiles($this->matchesPatterns($path) ? [$path] : []);
    }

    protected function findInDirectory(string $path): DiscoveredFiles
    {
        $this->console->verbose("Scanning directory recursively: {$path}");
        foreach ($this->filter->describe() as $line) {
            $this->console->verbose($line);
        }

        $iterator = $this->createDirectoryIterator($path);
        if ($iterator === null) {
            $this->console->error("Cannot read directory: {$path}" . PHP_EOL);

            return new DiscoveredFiles([], [new UncheckedInput($path, UncheckedInput::UNREADABLE_DIRECTORY)]);
        }

        $found = [];
        $files = new SafeRecursiveIteratorIterator($iterator, $this->console);
        foreach ($files as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $this->accepts($file)) {
                $found[] = $file->getPathname();
            }
        }
        sort($found, SORT_STRING);

        return new DiscoveredFiles($found, $files->getUnchecked());
    }

    /**
     * @return RecursiveIterator<mixed, mixed>|null null when the directory cannot be opened
     */
    protected function createDirectoryIterator(string $path): ?RecursiveIterator
    {
        try {
            return new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
        } catch (UnexpectedValueException) {
            return null;
        }
    }

    protected function accepts(SplFileInfo $file): bool
    {
        $filePath = $file->getPathname();
        if ($this->filter->isInExcludedDirectory($filePath)) {
            $this->console->verbose("File in excluded directory: {$filePath}");

            return false;
        }

        return preg_match(self::PHP_FILE, $file->getFilename()) === 1 && $this->matchesPatterns($filePath);
    }

    protected function matchesPatterns(string $filePath): bool
    {
        if ($this->filter->matchesPatterns($filePath)) {
            return true;
        }
        $this->console->verbose("File excluded by pattern: {$filePath}");

        return false;
    }
}
