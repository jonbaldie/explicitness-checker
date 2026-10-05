<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Tests\Support\CheckedFile;
use JonBaldie\ExplicitnessChecker\Tests\Support\Process;
use PHPUnit\Framework\TestCase;

/**
 * The CLI resolves class names against the namespace and `use` imports, as
 * PHPStan does, so both tools name the same static property identically.
 */
class NameResolutionTest extends TestCase
{
    public function testStaticPropertyClassNamesAreFullyQualified(): void
    {
        self::assertSame(
            [
                [
                    26,
                    'App\\Sub\\Consumer::reads',
                    [
                        'read from static property App\\Sub\\Registry::$items',
                        'read from static property Other\\Thing::$shared',
                        'read from static property Other\\Config::$values',
                    ],
                    [],
                ],
                [
                    31,
                    'App\\Sub\\Consumer::writes',
                    ['read from static property self::$calls'],
                    [
                        'wrote to static property App\\Sub\\Registry::$count',
                        'wrote to static property App\\Sub\\Nested\\Store::$cache',
                        'wrote to static property self::$calls',
                        'wrote to static property static::$calls',
                    ],
                ],
            ],
            CheckedFile::rows(Process::ROOT . '/test-fixtures/namespaced-static-property.php', ['--props']),
        );
    }
}
