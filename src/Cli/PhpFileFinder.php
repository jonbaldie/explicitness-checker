<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

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
        $this->console->verbose('Excluding directories: ' . implode(', ', $this->filter->getExcludeDirs()));
        $this->verboseIfSet('Include pattern: ', $this->filter->getIncludePattern());
        $this->verboseIfSet('Exclude pattern: ', $this->filter->getExcludePattern());

        $found = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $this->accepts($file)) {
                $found[] = $file->getPathname();
            }
        }
        sort($found, SORT_STRING);

        return $found;
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

    protected function verboseIfSet(string $label, ?string $value): void
    {
        if ($value !== null) {
            $this->console->verbose($label . $value);
        }
    }
}
