<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Tests\Support\Process;
use PHPUnit\Framework\TestCase;

/**
 * Subprocesses run by the tests see the environment CI sees. Tools such as
 * PHPStan change their output when they detect a coding agent, so a test
 * written under an agent could otherwise pass locally and fail on CI.
 */
class ProcessTest extends TestCase
{
    /**
     * @var array<string, string|false>
     */
    protected array $saved = [];

    protected function setUp(): void
    {
        foreach (Process::AGENT_VARIABLES as $name) {
            $this->saved[$name] = getenv($name);
            putenv($name . '=1');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $name => $value) {
            putenv($value === false ? $name : $name . '=' . $value);
        }
    }

    public function testSubprocessesDoNotSeeAgentVariables(): void
    {
        [$exitCode, $stdout] = Process::run([
            PHP_BINARY,
            '-r',
            'echo json_encode(array_map("getenv", array_slice($argv, 1)));',
            '--',
            ...Process::AGENT_VARIABLES,
        ]);

        self::assertSame(0, $exitCode);
        self::assertSame(json_encode(array_fill(0, count(Process::AGENT_VARIABLES), false)), $stdout);
    }

    public function testSubprocessesKeepTheRestOfTheEnvironment(): void
    {
        putenv('EXPLICITNESS_PROCESS_TEST=kept');
        try {
            [, $stdout] = Process::run([PHP_BINARY, '-r', 'echo getenv("EXPLICITNESS_PROCESS_TEST");']);
        } finally {
            putenv('EXPLICITNESS_PROCESS_TEST');
        }

        self::assertSame('kept', $stdout);
    }
}
