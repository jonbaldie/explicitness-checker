<?php

declare(strict_types=1);

/*
 * Loads the autoloader, then every class in src/ up front.
 *
 * Infection swaps a mutant in by intercepting the `file://` stream wrapper when
 * a source file is included. PHPStan's FileReadTrapStreamWrapper, used by the
 * RuleTestCase tests, restores PHP's own `file://` wrapper after reading a
 * file, which silently removes Infection's. Any src/ class first autoloaded
 * after that would load unmutated, so its mutants would survive every test.
 * Loading everything here, while the interceptor is still active, avoids that.
 */

require __DIR__ . '/../vendor/autoload.php';

$source = realpath(__DIR__ . '/../src');
assert(is_string($source));

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    assert($file instanceof SplFileInfo);
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $relative = substr($file->getPathname(), strlen($source) + 1, -strlen('.php'));
    class_exists('JonBaldie\\ExplicitnessChecker\\' . str_replace('/', '\\', $relative));
}
