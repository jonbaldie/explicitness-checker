<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The CLI resolves class names against the namespace and `use` imports, as
 * PHPStan does, so both tools name the same static property identically.
 */
class NameResolutionTest extends TestCase
{
    protected const ROOT = __DIR__ . '/..';

    public function testStaticPropertyClassNamesAreFullyQualified(): void
    {
        $command = [
            PHP_BINARY,
            self::ROOT . '/bin/explicitness-checker',
            '--props',
            self::ROOT . '/test-fixtures/namespaced-static-property.php',
        ];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, self::ROOT);
        self::assertIsResource($process);
        $output = (string) stream_get_contents($pipes[1]);
        $errors = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $rows = [];
        foreach (explode("\n", $output) as $line) {
            $cells = array_map('trim', explode('|', $line));
            if (count($cells) === 8 && $cells[1] === 'namespaced-static-property.php') {
                $rows[] = [$cells[2], $cells[3], $cells[4], $cells[5]];
            }
        }

        self::assertSame(
            [
                [
                    '26',
                    'App\\Sub\\Consumer::reads',
                    'read from static property App\\Sub\\Registry::$items; '
                    . 'read from static property Other\\Thing::$shared; '
                    . 'read from static property Other\\Config::$values',
                    '',
                ],
                [
                    '31',
                    'App\\Sub\\Consumer::writes',
                    'read from static property self::$calls',
                    'wrote to static property App\\Sub\\Registry::$count; '
                    . 'wrote to static property App\\Sub\\Nested\\Store::$cache; '
                    . 'wrote to static property self::$calls; '
                    . 'wrote to static property static::$calls',
                ],
            ],
            $rows,
        );
        self::assertSame(['', 2], [$errors, $exitCode]);
    }
}
