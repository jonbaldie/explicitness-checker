<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Tests\Support\Process;
use PHPUnit\Framework\TestCase;

/**
 * The CI checks in tools/, run as subprocesses against throwaway trees.
 */
class ToolsTest extends TestCase
{
    protected string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/explicitness-tools-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/src', 0777, true);
        mkdir($this->root . '/bin');
        mkdir($this->root . '/tests/Fixtures', 0777, true);
        file_put_contents($this->root . '/bin/explicitness-checker', "#!/usr/bin/env php\n<?php\n// private is fine in a comment\n");
        file_put_contents($this->root . '/src/Clean.php', "<?php\nclass Clean\n{\n    protected int \$x = 0;\n}\n");
        file_put_contents($this->root . '/tests/CleanTest.php', "<?php\n// createMock in a comment is fine\n");
        file_put_contents($this->root . '/tests/Fixtures/input.php', "<?php\nclass F { private \$x; }\n\$this->createMock(F::class);\n");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function testStandardsPassOnACleanTree(): void
    {
        [$exitCode, $stdout] = $this->standards();

        self::assertSame(0, $exitCode, $stdout);
    }

    public function testStandardsRejectPrivateInSource(): void
    {
        file_put_contents($this->root . '/src/Bad.php', "<?php\nclass Bad\n{\n    private int \$x = 0;\n}\n");

        [$exitCode, $stdout] = $this->standards();

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('src/Bad.php:4: private', $stdout);
    }

    public function testStandardsRejectPrivateInTheScript(): void
    {
        file_put_contents($this->root . '/bin/explicitness-checker', "#!/usr/bin/env php\n<?php\nnew class { private function f(): void {} };\n");

        [$exitCode, $stdout] = $this->standards();

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('bin/explicitness-checker:3: private', $stdout);
    }

    public function testStandardsRejectMocksInTests(): void
    {
        file_put_contents($this->root . '/tests/BadTest.php', "<?php\n\n\$this->getMockBuilder(Foo::class);\n");

        [$exitCode, $stdout] = $this->standards();

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('tests/BadTest.php:3: getMockBuilder', $stdout);
    }

    public function testStandardsRejectQualifiedMockingNames(): void
    {
        file_put_contents($this->root . '/tests/BadTest.php', "<?php\n\n\\Mockery::mock(Foo::class);\n");

        [$exitCode, $stdout] = $this->standards();

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('tests/BadTest.php:3: \\Mockery', $stdout);
    }

    public function testStandardsCheckEveryScriptInBin(): void
    {
        file_put_contents($this->root . '/bin/other', "<?php\nclass Other { private \$x; }\n");

        [$exitCode, $stdout] = $this->standards();

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('bin/other:2: private', $stdout);
    }

    public function testInfectionCheckPassesWithNoSkippedMutants(): void
    {
        [$exitCode, $stdout] = $this->infection(['stats' => ['skippedCount' => 0]]);

        self::assertSame(0, $exitCode, $stdout);
    }

    public function testInfectionCheckFailsOnSkippedMutants(): void
    {
        [$exitCode, $stdout] = $this->infection(['stats' => ['skippedCount' => 3]]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('3 mutants', $stdout);
    }

    public function testInfectionCheckFailsWithoutALog(): void
    {
        [$exitCode] = Process::run([PHP_BINARY, Process::ROOT . '/tools/check-infection-skips.php', $this->root . '/missing.json']);

        self::assertSame(1, $exitCode);
    }

    /**
     * @return array{int, string, string}
     */
    protected function standards(): array
    {
        return Process::run([PHP_BINARY, Process::ROOT . '/tools/check-standards.php', $this->root]);
    }

    /**
     * @param array<string, mixed> $log
     *
     * @return array{int, string, string}
     */
    protected function infection(array $log): array
    {
        file_put_contents($this->root . '/infection.json', json_encode($log));

        return Process::run([PHP_BINARY, Process::ROOT . '/tools/check-infection-skips.php', $this->root . '/infection.json']);
    }
}
