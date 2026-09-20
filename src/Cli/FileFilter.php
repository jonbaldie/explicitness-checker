<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

/**
 * Which PHP files to check: excluded directories (`--exclude`) and the
 * `--include-pattern` / `--exclude-pattern` regular expressions.
 *
 * A pattern that does not compile is reported by patternError() before any
 * file is matched, rather than failing once per candidate file.
 */
class FileFilter
{
    /**
     * The part of PCRE's warning that names the caller rather than the fault.
     */
    protected const COMPILE_PREFIX = 'preg_match(): Compilation failed: ';

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
     * Why the first unusable pattern does not compile, naming the option it
     * came from, or null when both patterns are usable. Callers must check
     * this before matching: matchesPatterns() assumes compilable patterns.
     */
    public function patternError(): ?string
    {
        return $this->compileError('--include-pattern', $this->includePattern)
            ?? $this->compileError('--exclude-pattern', $this->excludePattern);
    }

    /**
     * Compiles the pattern against an empty subject to see whether PCRE takes
     * it. PCRE reports a failure by returning false, but only explains it in a
     * warning, which is suppressed here and read back from the error state.
     */
    protected function compileError(string $option, ?string $pattern): ?string
    {
        if ($pattern === null) {
            return null;
        }

        error_clear_last();
        if (@preg_match($this->delimit($pattern), '') !== false) {
            return null;
        }
        $warning = error_get_last();

        return "Invalid {$option}: " . str_replace(self::COMPILE_PREFIX, '', $warning['message'] ?? '');
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
