<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

/**
 * Which PHP files to check: excluded directories (`--exclude`) and the
 * `--include-pattern` / `--exclude-pattern` regular expressions.
 */
class FileFilter
{
    /** Prefix preg_match() puts on its warnings, dropped from the reason reported. */
    protected const WARNING_PREFIX = 'preg_match(): ';

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
     * The filter's settings, one line each, for verbose output. The patterns
     * are only listed when set.
     *
     * @return list<string>
     */
    public function describe(): array
    {
        $lines = ['Excluding directories: ' . implode(', ', $this->excludeDirs)];
        if ($this->includePattern !== null) {
            $lines[] = 'Include pattern: ' . $this->includePattern;
        }
        if ($this->excludePattern !== null) {
            $lines[] = 'Exclude pattern: ' . $this->excludePattern;
        }

        return $lines;
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
     * The reason the patterns cannot be used, naming the flag whose regex does
     * not compile, or null when every pattern given compiles. Checked once
     * before the file walk, so a bad pattern fails the run instead of making
     * preg_match() warn per candidate file.
     */
    public function patternError(): ?string
    {
        $patterns = ['--include-pattern' => $this->includePattern, '--exclude-pattern' => $this->excludePattern];
        foreach ($patterns as $flag => $pattern) {
            $reason = $pattern === null ? null : $this->compileError($this->delimit($pattern));
            if ($reason !== null) {
                return "Invalid {$flag}: {$reason}";
            }
        }

        return null;
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

    /**
     * Compiles the delimited pattern against an empty subject, returning
     * PCRE's complaint (without preg_match()'s own prefix) or null when the
     * pattern compiles. The warning is read back rather than printed.
     */
    protected function compileError(string $delimited): ?string
    {
        if (@preg_match($delimited, '') !== false) {
            return null;
        }
        $message = error_get_last()['message'] ?? '';

        return str_starts_with($message, self::WARNING_PREFIX)
            ? substr($message, strlen(self::WARNING_PREFIX))
            : $message;
    }
}
