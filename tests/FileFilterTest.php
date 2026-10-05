<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Cli\FileFilter;
use PHPUnit\Framework\TestCase;

class FileFilterTest extends TestCase
{
    public function testAFilterBuiltWithoutSettingsExcludesVendorAndNothingElse(): void
    {
        $filter = new FileFilter();

        self::assertTrue($filter->isInExcludedDirectory('vendor/package/src/Thing.php'));
        self::assertFalse($filter->isInExcludedDirectory('src/Thing.php'));
        self::assertTrue($filter->matchesPatterns('src/Thing.php'));
        self::assertSame(['Excluding directories: vendor'], $filter->describe());
    }
}
