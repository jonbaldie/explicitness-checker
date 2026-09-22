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
     * Every occurrence of a pattern flag is kept: a file must match one of the
     * include patterns (when any are given) and none of the exclude patterns.
     *
     * @param list<string> $excludeDirs
     * @param list<string> $includePatterns regex bodies, without delimiters
     * @param list<string> $excludePatterns regex bodies, without delimiters
     */
    public function __construct(
        protected array $excludeDirs,
        protected array $includePatterns,
        protected array $excludePatterns,
    ) {
    }

    /**
     * The filter's settings, one line each, for verbose output. Each pattern
     * given gets its own line, in the order it was given.
     *
     * @return list<string>
     */
    public function describe(): array
    {
        $lines = ['Excluding directories: ' . implode(', ', $this->excludeDirs)];
        foreach ($this->includePatterns as $pattern) {
            $lines[] = 'Include pattern: ' . $pattern;
        }
        foreach ($this->excludePatterns as $pattern) {
            $lines[] = 'Exclude pattern: ' . $pattern;
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
     * The reason an excluded directory cannot be used, or null when every
     * directory name contains a path segment.
     */
    public function directoryError(): ?string
    {
        foreach ($this->excludeDirs as $excludeDir) {
            if (trim($excludeDir, '/\\') === '') {
                return 'Invalid --exclude: directory name is empty';
            }
        }

        return null;
    }

    /**
     * The reason the patterns cannot be used, naming the flag whose regex does
     * not compile, or null when every pattern given compiles. Every occurrence
     * of a flag is checked, so a bad pattern is caught wherever it was given.
     * Checked once before the file walk, so a bad pattern fails the run instead
     * of making preg_match() warn per candidate file.
     */
    public function patternError(): ?string
    {
        $flags = ['--include-pattern' => $this->includePatterns, '--exclude-pattern' => $this->excludePatterns];
        foreach ($flags as $flag => $patterns) {
            $reason = $this->firstCompileError($patterns);
            if ($reason !== null) {
                return "Invalid {$flag}: {$reason}";
            }
        }

        return null;
    }

    /**
     * Whether the path matches one of the include patterns (when any were
     * given) and none of the exclude patterns.
     */
    public function matchesPatterns(string $filePath): bool
    {
        if ($this->includePatterns !== [] && !$this->matchesAny($this->includePatterns, $filePath)) {
            return false;
        }

        return !$this->matchesAny($this->excludePatterns, $filePath);
    }

    /**
     * Whether the path matches at least one of the patterns. No patterns means
     * no match.
     *
     * @param list<string> $patterns regex bodies, without delimiters
     */
    protected function matchesAny(array $patterns, string $filePath): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($this->delimit($pattern), $filePath) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * PCRE's complaint about the first pattern in the list that does not
     * compile, or null when they all do.
     *
     * @param list<string> $patterns regex bodies, without delimiters
     */
    protected function firstCompileError(array $patterns): ?string
    {
        foreach ($patterns as $pattern) {
            $reason = $this->compileError($this->delimit($pattern));
            if ($reason !== null) {
                return $reason;
            }
        }

        return null;
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
