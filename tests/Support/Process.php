<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests\Support;

use RuntimeException;

/**
 * Runs a command as a subprocess from the repository root, for tests that
 * drive the real CLI or PHPStan.
 */
class Process
{
    public const ROOT = __DIR__ . '/../..';

    /**
     * The variables PHPStan uses to detect a coding agent (it then prints
     * error identifiers in raw output). Copied from PHPStan\Internal\AgentDetector,
     * which is internal to PHPStan.
     */
    protected const AGENT_VARIABLES = [
        'AUGMENT_AGENT', 'AMP_CURRENT_THREAD_ID', 'AI_AGENT', 'CURSOR_TRACE_ID', 'CURSOR_AGENT',
        'GEMINI_CLI', 'CODEX_SANDBOX', 'CODEX_THREAD_ID', 'OPENCODE_CLIENT', 'OPENCODE',
        'CLAUDECODE', 'CLAUDE_CODE', 'REPL_ID',
    ];

    /**
     * Runs bin/explicitness-checker with the given arguments.
     *
     * @param list<string> $arguments
     *
     * @return array{int, string, string} exit code, stdout, stderr
     */
    public static function cli(array $arguments): array
    {
        return self::run(array_merge([PHP_BINARY, self::ROOT . '/bin/explicitness-checker'], $arguments));
    }

    /**
     * Standard error goes to a temporary file rather than a pipe, so a chatty
     * command can't fill one pipe while this reads the other.
     *
     * @param list<string> $command
     *
     * @return array{int, string, string} exit code, stdout, stderr
     */
    public static function run(array $command): array
    {
        $stderr = tmpfile();
        if ($stderr === false) {
            throw new RuntimeException('Could not create a temporary file for standard error');
        }
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => $stderr], $pipes, self::ROOT, self::environment());
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start ' . implode(' ', $command));
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exitCode = proc_close($process);

        rewind($stderr);
        $errors = (string) stream_get_contents($stderr);
        fclose($stderr);

        return [$exitCode, $stdout, $errors];
    }

    /**
     * The current environment without the agent variables, so subprocesses
     * behave as they do on CI.
     *
     * @return array<string, string>
     */
    protected static function environment(): array
    {
        return array_diff_key(getenv(), array_flip(self::AGENT_VARIABLES));
    }
}
