<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

/**
 * Which PHP files to check: excluded directories (`--exclude`) and the
 * `--include-pattern` / `--exclude-pattern` regular expressions.
 */
class FileFilter
{
    /**
     * @param list<string> $excludeDirs
     * @param string|null  $includePattern regex body, without delimiters
     * @param string|null  $excludePattern regex body, without delimiters
     */
    public function __construct(
        protected array $excludeDirs,
        protected ?string $includePattern,
        protected ?string $excludePattern,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getExcludeDirs(): array
    {
        return $this->excludeDirs;
    }

    public function getIncludePattern(): ?string
    {
        return $this->includePattern;
    }

    public function getExcludePattern(): ?string
    {
        return $this->excludePattern;
    }

    /**
     * Whether the path contains an excluded directory as a whole path segment,
     * or starts with one.
     */
    public function isInExcludedDirectory(string $filePath): bool
    {
        $normalizedPath = str_replace('\\', '/', $filePath);
        foreach ($this->excludeDirs as $excludeDir) {
            $excludeDir = trim($excludeDir, '/\\');
            $segment = DIRECTORY_SEPARATOR . $excludeDir . DIRECTORY_SEPARATOR;
            if (str_contains($filePath, $segment) || str_starts_with($normalizedPath, $excludeDir . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the path matches the include pattern (if any) and not the
     * exclude pattern (if any).
     */
    public function matchesPatterns(string $filePath): bool
    {
        if ($this->includePattern !== null && !preg_match($this->delimit($this->includePattern), $filePath)) {
            return false;
        }

        return $this->excludePattern === null || !preg_match($this->delimit($this->excludePattern), $filePath);
    }

    /**
     * Wrap a user-supplied regex body in "/" delimiters, escaping any bare "/"
     * so patterns like "src/.*\.php$" work. Existing escapes (e.g. "\/") are kept.
     */
    protected function delimit(string $pattern): string
    {
        return '/' . preg_replace('~\\\\.(*SKIP)(*FAIL)|/~s', '\\/', $pattern) . '/';
    }
}
