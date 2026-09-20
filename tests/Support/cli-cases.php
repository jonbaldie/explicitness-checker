<?php

declare(strict_types=1);

/*
 * CLI invocations whose stdout, stderr and exit code are pinned byte for byte
 * in tests/Fixtures/cli-expected/. Arguments exclude the script name; paths are
 * relative to the repository root, which is the working directory for every run.
 *
 * Verbose runs over directories only use trees where at most one file is
 * skipped while scanning: those messages follow the file system's directory
 * order, which differs between platforms.
 *
 * @return array<string, list<string>>
 */

$modes = [
    'default' => [],
    'verbose' => ['-v'],
    'strict' => ['--strict'],
    'props' => ['--props'],
    'strict-props' => ['--strict', '--props'],
    'verbose-strict-props' => ['--verbose', '--strict', '--props'],
];

$targets = [
    'bad' => 'test-fixtures/bad-examples.php',
    'good' => 'test-fixtures/good-examples.php',
    'strict-examples' => 'test-fixtures/strict-examples.php',
    'bodyless' => 'test-fixtures/bodyless-methods.php',
    'closure-global-bleed' => 'test-fixtures/closure-global-bleed.php',
    'env-access' => 'test-fixtures/env-access.php',
    'repeated-input' => 'test-fixtures/repeated-input.php',
    'scope-global' => 'test-fixtures/scope-global.php',
    'scope-namespaced' => 'test-fixtures/scope-namespaced.php',
    'trait-method' => 'test-fixtures/trait-method.php',
    'namespaced-static-property' => 'test-fixtures/namespaced-static-property.php',
    'rw-array-index' => 'test-fixtures/read-write-context/array-index.php',
    'rw-foreach-catch' => 'test-fixtures/read-write-context/foreach-catch.php',
    'rw-global-declaration' => 'test-fixtures/read-write-context/global-declaration.php',
    'rw-unset' => 'test-fixtures/read-write-context/unset.php',
    'rw-operators-and-bodies' => 'test-fixtures/read-write-context/operators-and-bodies.php',
    'nested-declarations' => 'test-fixtures/nested-declarations.php',
    'minor-only' => 'tests/Fixtures/cli/minor-only.php',
    'parse-error' => 'tests/Fixtures/cli/parse-error.php',
    'fixtures-dir' => 'test-fixtures',
    'cli-dir' => 'tests/Fixtures/cli',
];

$cases = [];
foreach ($targets as $targetName => $target) {
    foreach ($modes as $modeName => $flags) {
        $cases[$targetName . '.' . $modeName] = array_merge($flags, [$target]);
    }
}

return $cases + [
    // Usage and invalid paths.
    'no-arguments' => [],
    'flags-only' => ['-v', '--strict'],
    'missing-path' => ['does-not-exist.php'],
    'missing-path.verbose' => ['-v', 'does-not-exist'],
    'empty-path' => [''],
    'non-php-file' => ['README.md'],
    'non-php-file.verbose' => ['-v', 'README.md'],

    // Argument parsing.
    'unknown-flags-ignored' => ['--unknown', '-x', '-', 'test-fixtures/env-access.php'],
    'second-path-ignored' => ['test-fixtures/env-access.php', 'test-fixtures/bad-examples.php'],
    'flags-after-path' => ['test-fixtures/strict-examples.php', '--strict'],
    'trailing-slash-dir' => ['-v', 'tests/Fixtures/cli/'],
    'exclude-equals' => ['--exclude=read-write-context', 'test-fixtures'],
    'exclude-separate' => ['--exclude', 'read-write-context', 'test-fixtures'],
    'exclude-slashes-trimmed' => ['--exclude=/read-write-context/', 'test-fixtures'],
    'exclude-missing-value' => ['test-fixtures/env-access.php', '--exclude'],
    'exclude-path-prefix' => ['--exclude=tests', 'tests/Fixtures/cli'],
    'exclude-path-prefix.verbose' => ['-v', '--exclude=tests/Fixtures/cli/nested', 'tests/Fixtures/cli/nested'],
    'exclude-other-dir.verbose' => ['-v', '--exclude=nothing-here', 'tests/Fixtures/cli'],
    'exclude-empty' => ['--exclude=', 'test-fixtures'],
    'vendor-dir-itself' => ['tests/Fixtures/cli/vendor'],

    // Include and exclude patterns (#4).
    'include-pattern-equals' => ['--include-pattern=read-write-context/', 'test-fixtures'],
    'include-pattern-separate' => ['--include-pattern', 'env-access\.php$', 'test-fixtures'],
    'include-pattern-slash-file' => ['--include-pattern=test-fixtures/', 'test-fixtures/env-access.php'],
    'include-pattern-escaped-slash-file' => ['--include-pattern=test-fixtures\/env', 'test-fixtures/env-access.php'],
    'include-pattern-readme-example' => ['--include-pattern=test-fixtures/.*\.php$', 'test-fixtures/env-access.php'],
    'include-pattern-slash-directory' => ['--include-pattern=/env-access\.php$', 'test-fixtures'],
    'include-pattern-escaped-slash' => ['--include-pattern=test-fixtures\/env', 'test-fixtures'],
    'include-pattern-no-match-file' => ['--include-pattern=src/', 'test-fixtures/env-access.php'],
    'include-pattern-no-match-file.verbose' => ['-v', '--include-pattern=src/', 'test-fixtures/env-access.php'],
    'include-pattern-match-file.verbose' => ['-v', '--include-pattern=env', 'test-fixtures/env-access.php'],
    'include-pattern-missing-value' => ['test-fixtures/env-access.php', '--include-pattern'],
    'include-pattern-empty' => ['--include-pattern=', 'test-fixtures/env-access.php'],
    'include-pattern-last-wins' => ['--include-pattern=nothing', '--include-pattern=env', 'test-fixtures'],
    'exclude-pattern-equals' => ['--exclude-pattern=read-write-context/', 'test-fixtures'],
    'exclude-pattern-separate' => ['--exclude-pattern', 'examples', 'test-fixtures'],
    'exclude-pattern-file' => ['--exclude-pattern=test-fixtures/', 'test-fixtures/env-access.php'],
    'exclude-pattern-file.verbose' => ['-v', '--exclude-pattern=test-fixtures/', 'test-fixtures/env-access.php'],
    'exclude-pattern-other-file' => ['--exclude-pattern=src/', 'test-fixtures/env-access.php'],
    'exclude-pattern-missing-value' => ['test-fixtures/env-access.php', '--exclude-pattern'],
    'exclude-pattern-dir.verbose' => ['-v', '--exclude-pattern=UpperCase', 'tests/Fixtures/cli/nested'],
    'include-pattern-dir.verbose' => ['-v', '--include-pattern=nothing', 'tests/Fixtures/cli/nested'],
    'both-patterns' => ['--include-pattern=read-write-context', '--exclude-pattern=unset', 'test-fixtures'],
    'both-patterns.verbose' => ['-v', '--include-pattern=nested', '--exclude-pattern=Upper', 'tests/Fixtures/cli/nested'],

    // Patterns that do not compile (#22).
    'include-pattern-invalid' => ['--include-pattern=src/(', 'test-fixtures'],
    'include-pattern-invalid.verbose' => ['-v', '--include-pattern=src/(', 'test-fixtures'],
    'exclude-pattern-invalid' => ['--exclude-pattern=[', 'test-fixtures'],
    'both-patterns-invalid' => ['--include-pattern=src/(', '--exclude-pattern=[', 'test-fixtures'],
    'invalid-pattern-missing-path' => ['--include-pattern=src/(', 'does-not-exist'],

    // Same-basename files must stay distinct in the File column (#24).
    'same-basename' => ['tests/Fixtures/same-basename'],
    'same-basename.file' => ['tests/Fixtures/same-basename/src/Calculator.php'],
];
