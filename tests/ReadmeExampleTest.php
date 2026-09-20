<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Cli\Application;
use JonBaldie\ExplicitnessChecker\Tests\Support\Process;
use PHPUnit\Framework\TestCase;

/**
 * Regression for #25: the README headline example must be PHP the CLI
 * reports, and "Example Output" must be a real transcript of that run.
 */
class ReadmeExampleTest extends TestCase
{
    public function testHeadlineExampleReportsGlobalReadAndWrite(): void
    {
        [$exitCode, $stdout] = $this->runHeadlineExample();

        self::assertSame(2, $exitCode, $stdout);
        self::assertStringContainsString('read from global variable $some_global_number', $stdout);
        self::assertStringContainsString('wrote to global variable $some_global_number', $stdout);
        self::assertStringContainsString('| Serious  |', $stdout);
    }

    public function testExampleOutputMatchesTheHeadlineRun(): void
    {
        [, $stdout] = $this->runHeadlineExample();

        self::assertSame($this->exampleOutputTranscript(), $stdout);
    }

    /**
     * @return array{int, string}
     */
    protected function runHeadlineExample(): array
    {
        $root = $this->scratchProject();
        $cwd = getcwd();
        self::assertIsString($cwd);
        $out = fopen('php://memory', 'w+');
        $err = fopen('php://memory', 'w+');
        self::assertIsResource($out);
        self::assertIsResource($err);
        chdir($root);
        try {
            $exitCode = (new Application($out, $err))->run([
                'bin/explicitness-checker',
                './path/to/your/project',
            ]);
        } finally {
            chdir($cwd);
        }
        rewind($out);

        return [$exitCode, (string) stream_get_contents($out)];
    }

    protected function scratchProject(): string
    {
        $root = sys_get_temp_dir() . '/ec-readme-' . bin2hex(random_bytes(8));
        $dir = $root . '/path/to/your/project';
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/Calculator.php', $this->headlineSnippet());

        return $root;
    }

    protected function headlineSnippet(): string
    {
        self::assertSame(
            1,
            preg_match('/```php\n(\$some_global_number = 10;.*?)\n```/s', $this->readme(), $match),
            'README headline add() snippet not found',
        );

        return "<?php\n" . $match[1] . "\n";
    }

    protected function exampleOutputTranscript(): string
    {
        self::assertSame(
            1,
            preg_match('/### Example Output\n\n```\n(.*?)\n```/s', $this->readme(), $match),
            'README Example Output block not found',
        );
        $prefix = "$ ./vendor/bin/explicitness-checker ./path/to/your/project\n\n";
        self::assertStringStartsWith($prefix, $match[1]);

        return substr($match[1], strlen($prefix)) . "\n";
    }

    protected function readme(): string
    {
        return (string) file_get_contents(Process::ROOT . '/README.md');
    }
}
