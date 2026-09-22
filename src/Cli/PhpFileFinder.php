<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

use RecursiveArrayIterator;
use RecursiveDirectoryIterator;
use RecursiveIterator;
use RecursiveIteratorIterator;
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

    /**
     * @return list<string> file paths, sorted
     */
    public function find(string $path): array
    {
        if (is_file($path)) {
            return $this->findFile($path);
        }

        return $this->findInDirectory($path);
    }

    /**
     * @return list<string>
     */
    protected function findFile(string $path): array
    {
        if (!preg_match(self::PHP_FILE, $path)) {
            $this->console->verbose("Path is file but not PHP: {$path}");

            return [];
        }

        $this->console->verbose("Path is file and ends with .php: {$path}");

        return $this->matchesPatterns($path) ? [$path] : [];
    }

    /**
     * @return list<string>
     */
    protected function findInDirectory(string $path): array
    {
        $this->console->verbose("Scanning directory recursively: {$path}");
        foreach ($this->filter->describe() as $line) {
            $this->console->verbose($line);
        }

        $found = [];
        $files = new SafeRecursiveIteratorIterator($this->createDirectoryIterator($path), $this->console);
        foreach ($files as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $this->accepts($file)) {
                $found[] = $file->getPathname();
            }
        }
        sort($found, SORT_STRING);

        return $found;
    }

    /**
     * @return RecursiveIterator<mixed, mixed>
     */
    protected function createDirectoryIterator(string $path): RecursiveIterator
    {
        try {
            return new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
        } catch (UnexpectedValueException) {
            $this->console->error("Cannot read directory: {$path}" . PHP_EOL);

            return new RecursiveArrayIterator([]);
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
