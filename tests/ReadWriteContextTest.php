<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Regression tests for whether an access is a read or a write (#5, #6, #9, #10).
 *
 * Each test runs the real CLI with --props on a fixture in
 * test-fixtures/read-write-context/ and compares every reported row.
 */
class ReadWriteContextTest extends TestCase
{
    protected const ROOT = __DIR__ . '/..';

    /**
     * #5: the index of an assigned array element is read; only the array is written.
     */
    public function testArrayIndexOnAssignmentTargetIsRead(): void
    {
        self::assertSame(
            [
                'indexBySuperglobal' => ['read from superglobal $_GET', ''],
                'writeGlobalAtGlobalIndex' => ['read from global variable $key', 'wrote to global variable $map'],
                'nestedIndexes' => [
                    'read from superglobal $_GET; read from superglobal $_POST',
                    'wrote to superglobal $_SESSION',
                ],
                'IndexedCache::put' => ['read from superglobal $_COOKIE', 'wrote to object property $this->items'],
            ],
            $this->reportedRows('array-index.php'),
        );
    }

    /**
     * #6: `global $x;` alone is not a read.
     */
    public function testGlobalDeclarationIsNotARead(): void
    {
        self::assertSame(
            [
                'writeOnly' => ['', 'wrote to global variable $counter'],
                'readAndWrite' => ['read from global variable $total', 'wrote to global variable $total'],
            ],
            $this->reportedRows('global-declaration.php'),
        );
    }

    /**
     * #9: foreach key/value targets and catch variables are written.
     */
    public function testForeachTargetsAndCatchVariableAreWritten(): void
    {
        self::assertSame(
            [
                'iterateIntoGlobal' => ['read from global variable $items', 'wrote to global variable $item'],
                'iterateKeysIntoGlobal' => ['read from superglobal $_POST', 'wrote to global variable $position'],
                'catchIntoGlobal' => ['', 'wrote to global variable $lastError'],
            ],
            $this->reportedRows('foreach-catch.php'),
        );
    }

    /**
     * #10: unset() writes to what it unsets.
     */
    public function testUnsetIsAWrite(): void
    {
        self::assertSame(
            [
                'logout' => ['', 'wrote to superglobal $_SESSION'],
                'forgetGlobal' => ['', 'wrote to global variable $cache'],
                'forgetGlobalsEntry' => ['', "wrote to \$GLOBALS['registry']"],
                'Memo::clear' => ['', 'wrote to object property $this->cached'],
            ],
            $this->reportedRows('unset.php'),
        );
    }

    /**
     * Runs the CLI with --props on the fixture and returns its result rows.
     *
     * @return array<string, array{string, string}> function name => [inputs, outputs]
     */
    protected function reportedRows(string $fixture): array
    {
        $command = [
            PHP_BINARY,
            self::ROOT . '/bin/explicitness-checker',
            '--props',
            self::ROOT . '/test-fixtures/read-write-context/' . $fixture,
        ];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, self::ROOT);
        self::assertIsResource($process);
        $output = (string) stream_get_contents($pipes[1]);
        $errors = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        self::assertSame('', $errors);

        $rows = [];
        foreach (explode("\n", $output) as $line) {
            $cells = array_map('trim', explode('|', $line));
            if (count($cells) !== 8 || $cells[1] !== $fixture) {
                continue;
            }
            $rows[$cells[3]] = [$cells[4], $cells[5]];
        }

        return $rows;
    }
}
