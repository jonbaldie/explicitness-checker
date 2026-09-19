<?php

declare(strict_types=1);

/*
 * The mechanical rules in CODING_STANDARDS.md: production code (src/, bin/)
 * uses protected rather than private, and tests (outside tests/Fixtures/) don't
 * mock. Matches PHP tokens, so comments and strings don't count.
 *
 * Usage: php tools/check-standards.php [repository root]
 * Prints one line per violation and exits 1 if there are any.
 */

const MOCKING = ['createMock', 'createPartialMock', 'createConfiguredMock', 'createStub', 'getMockBuilder',
    'getMockForAbstractClass', 'getMockForTrait', 'prophesize', 'Mockery'];

/**
 * @return list<string> paths below $directory ending in .php, skipping $skip
 */
function phpFiles(string $directory, ?string $skip = null): array
{
    $files = [];
    $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($entries as $entry) {
        if (!$entry instanceof SplFileInfo) {
            continue;
        }
        $path = $entry->getPathname();
        if (substr($path, -4) === '.php' && ($skip === null || strpos($path, $skip) !== 0)) {
            $files[] = $path;
        }
    }
    sort($files);

    return $files;
}

/**
 * @param callable(array{int, string, int}): bool $isViolation
 *
 * @return list<string> "path:line: token" for each matching token
 */
function violations(string $root, string $path, callable $isViolation): array
{
    $found = [];
    foreach (token_get_all((string) file_get_contents($path)) as $token) {
        if (is_array($token) && $isViolation($token)) {
            $found[] = substr($path, strlen($root) + 1) . ':' . $token[2] . ': ' . $token[1];
        }
    }

    return $found;
}

$root = rtrim($argv[1] ?? dirname(__DIR__), '/');

$violations = [];
foreach (array_merge([$root . '/bin/explicitness-checker'], phpFiles($root . '/src')) as $path) {
    $violations = array_merge($violations, violations($root, $path, fn (array $token): bool => $token[0] === T_PRIVATE));
}
foreach (phpFiles($root . '/tests', $root . '/tests/Fixtures/') as $path) {
    $violations = array_merge($violations, violations(
        $root,
        $path,
        fn (array $token): bool => $token[0] === T_STRING && in_array($token[1], MOCKING, true),
    ));
}

foreach ($violations as $violation) {
    echo $violation, PHP_EOL;
}
if ($violations !== []) {
    echo 'Use protected rather than private, and real objects rather than mocks (CODING_STANDARDS.md).', PHP_EOL;
    exit(1);
}
