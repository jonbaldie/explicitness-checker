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
    'fopen-modes' => 'tests/Fixtures/fopen-modes.php',
    'bodyless' => 'test-fixtures/bodyless-methods.php',
    'closure-global-bleed' => 'test-fixtures/closure-global-bleed.php',
    'env-access' => 'test-fixtures/env-access.php',
    'repeated-input' => 'test-fixtures/repeated-input.php',
    'scope-global' => 'test-fixtures/scope-global.php',
    'scope-namespaced' => 'test-fixtures/scope-namespaced.php',
    'trait-method' => 'test-fixtures/trait-method.php',
    'property-hooks' => 'test-fixtures/property-hooks.php',
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
    'exit-forms.strict' => ['--strict', 'tests/Fixtures/exit-forms.php'],

    // #47: nested anonymous classes keep their named enclosing class in
    // method and property-hook names.
    'nested-anonymous-classes' => ['tests/Fixtures/nested-anonymous-classes.php'],

    // #46: PHP function names are case-insensitive, so catalogue calls written
    // in any casing must be detected in strict mode.
    'case-insensitive-functions.strict' => ['--strict', 'tests/Fixtures/case-insensitive-functions.php'],

    // A static call with no arguments gets its data from outside the function's
    // arguments, so it is an implicit input in default mode.
    'static-call.default' => ['tests/Fixtures/static-call.php'],

    // #72: writes through arguments, static variables and by-reference
    // captures are shared state, so they are serious in default mode. Static
    // properties are too: see namespaced-static-property.default.
    'argument-mutation.default' => ['tests/Fixtures/argument-mutation.php'],
    'static-variable.default' => ['tests/Fixtures/static-variable.php'],
    'captured-reference.default' => ['tests/Fixtures/captured-reference.php'],

    // #72: network, database, process, mail, include and runtime-config
    // findings are critical in strict mode.
    'network.strict' => ['--strict', 'tests/Fixtures/network.php'],
    'database.strict' => ['--strict', 'tests/Fixtures/database.php'],
    'process.strict' => ['--strict', 'tests/Fixtures/process.php'],
    'mail.strict' => ['--strict', 'tests/Fixtures/mail.php'],
    'include.strict' => ['--strict', 'tests/Fixtures/include.php'],
    'runtime-config.strict' => ['--strict', 'tests/Fixtures/runtime-config.php'],

    // Usage and invalid paths.
    'no-arguments' => [],
    'flags-only' => ['-v', '--strict'],
    'missing-path' => ['does-not-exist.php'],
    'missing-path.verbose' => ['-v', 'does-not-exist'],
    'empty-path' => [''],
    'non-php-file' => ['README.md'],
    'non-php-file.verbose' => ['-v', 'README.md'],

    // Argument parsing.
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
    'exclude-slash-only' => ['--exclude=/', 'test-fixtures'],
    'exclude-separate-empty' => ['--exclude', '', 'test-fixtures'],
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
    'include-pattern-one-matching' => ['--include-pattern=nothing', '--include-pattern=env', 'test-fixtures'],
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

    // Repeating a pattern option accumulates, like repeating --exclude (#26).
    'include-patterns-accumulate' => ['--include-pattern=env-access', '--include-pattern=repeated-input', 'test-fixtures'],
    'exclude-patterns-accumulate' => ['--exclude-pattern=read-write-context', '--exclude-pattern=bad-examples', 'test-fixtures'],
    'exclude-patterns-accumulate.verbose' => ['-v', '--exclude-pattern=Upper', '--exclude-pattern=nothing', 'tests/Fixtures/cli/nested'],
    'exclude-patterns-first-invalid' => ['--exclude-pattern=[', '--exclude-pattern=env', 'test-fixtures'],
    'include-patterns-first-invalid' => ['--include-pattern=src/(', '--include-pattern=env', 'test-fixtures'],

    // Patterns that do not compile (#22).
    'include-pattern-invalid' => ['--include-pattern=src/(', 'test-fixtures'],
    'include-pattern-invalid.verbose' => ['-v', '--include-pattern=src/(', 'test-fixtures'],
    'exclude-pattern-invalid' => ['--exclude-pattern=[', 'test-fixtures'],
    'both-patterns-invalid' => ['--include-pattern=src/(', '--exclude-pattern=[', 'test-fixtures'],
    'invalid-pattern-missing-path' => ['--include-pattern=src/(', 'does-not-exist'],

    // Unknown options stop the run instead of being ignored (#23).
    'unknown-option' => ['--stict', 'tests/Fixtures/cli/minor-only.php'],
    'unknown-option-after-path' => ['tests/Fixtures/cli/minor-only.php', '--prop'],
    'unknown-option-with-value' => ['--exlude-pattern=minor', 'tests/Fixtures/cli/minor-only.php'],
    'unknown-short-option' => ['-x', 'tests/Fixtures/cli/minor-only.php'],
    'unknown-option-lone-dash' => ['-', 'tests/Fixtures/cli/minor-only.php'],
    'unknown-option-missing-path' => ['--stict'],
    'unknown-option-before-invalid-pattern' => ['--include-pattern=src/(', '--stict', 'test-fixtures'],
    'known-option-value-with-dash' => ['--exclude-pattern', '-nothing', '--strict', 'tests/Fixtures/cli/minor-only.php'],

    // Same-basename files must stay distinct in the File column (#24).
    'same-basename' => ['tests/Fixtures/same-basename'],
    'same-basename.file' => ['tests/Fixtures/same-basename/src/Calculator.php'],

    // A parse failure must fail the run without stopping readable files.
    'parse-error-with-clean' => ['--include-pattern=(parse-error|minor-only)', 'tests/Fixtures/cli'],
    'parse-error-with-minor' => ['--strict', '--include-pattern=(parse-error|minor-only)', 'tests/Fixtures/cli'],
    'parse-error-with-critical' => ['--include-pattern=(parse-error|env-access)', '.'],
    // Explicitness percentage gate (#64). two-of-three.php checks 3
    // function-likes, 2 of them explicit.
    'min-explicitness-met' => ['--min-explicitness=50', 'tests/Fixtures/explicitness/two-of-three.php'],
    'min-explicitness-unmet' => ['--min-explicitness=70', 'tests/Fixtures/explicitness/two-of-three.php'],
    'min-explicitness-clean' => ['--min-explicitness=100', 'tests/Fixtures/cli/minor-only.php'],
    'min-explicitness-none-checked' => ['--min-explicitness=80', 'test-fixtures/bodyless-methods.php'],
    // Under --strict, minor-only.php has 1 explicit function-like of 2.
    'min-explicitness-strict-boundary' => ['--strict', '--min-explicitness=50', 'tests/Fixtures/cli/minor-only.php'],
    'min-explicitness-strict-unmet' => ['--strict', '--min-explicitness=51', 'tests/Fixtures/cli/minor-only.php'],
    'min-explicitness-decimal-boundary' => ['--min-explicitness=87.5', 'tests/Fixtures/explicitness/seven-of-eight.php'],
    'min-explicitness-decimal-unmet' => ['--min-explicitness=87.51', 'tests/Fixtures/explicitness/seven-of-eight.php'],
    'min-explicitness-separate-value' => ['--min-explicitness', '87.5', 'tests/Fixtures/explicitness/seven-of-eight.php'],
    // 2 of 3 is 66.666...%: the gate compares exactly, not the rounded-down
    // 66.6% it prints, and not through floats.
    'min-explicitness-above-displayed' => ['--min-explicitness=66.66', 'tests/Fixtures/explicitness/two-of-three.php'],
    'min-explicitness-long-decimal' => ['--min-explicitness=66.66666666666666666666', 'tests/Fixtures/explicitness/two-of-three.php'],
    'min-explicitness-long-decimal-unmet' => ['--min-explicitness=66.66666666666666666667', 'tests/Fixtures/explicitness/two-of-three.php'],
    'min-explicitness-normalised' => ['--min-explicitness=087.50', 'tests/Fixtures/explicitness/seven-of-eight.php'],
    'min-explicitness-zero' => ['--min-explicitness=00.000', 'tests/Fixtures/explicitness/seven-of-eight.php'],
    'min-explicitness-hundred-point-zero' => ['--min-explicitness=100.0', 'tests/Fixtures/cli/minor-only.php'],
    'min-explicitness-hundred-unmet' => ['--min-explicitness=100', 'tests/Fixtures/explicitness/two-of-three.php'],
    'min-explicitness-bare-zero' => ['--min-explicitness=0', 'tests/Fixtures/explicitness/two-of-three.php'],
    'min-explicitness-last-wins' => ['--min-explicitness=99', '--min-explicitness=50', 'tests/Fixtures/explicitness/two-of-three.php'],
    // tests/Fixtures/cli has the same 2 of 3 plus a file that fails to parse:
    // it counts in neither total, and a met gate still exits 2 for it.
    'min-explicitness-parse-error' => ['--min-explicitness=50', 'tests/Fixtures/cli'],
    // With no value the flag is ignored, as the other value flags are.
    'min-explicitness-missing-value' => ['test-fixtures/env-access.php', '--min-explicitness'],
    // A minimum that isn't a plain decimal number from 0 to 100 is bad usage.
    'min-explicitness-invalid-word' => ['--min-explicitness=abc', 'test-fixtures/env-access.php'],
    'min-explicitness-invalid-negative' => ['--min-explicitness=-5', 'test-fixtures/env-access.php'],
    'min-explicitness-invalid-negative-separate' => ['--min-explicitness', '-5', 'test-fixtures/env-access.php'],
    'min-explicitness-invalid-above-100' => ['--min-explicitness=100.5', 'test-fixtures/env-access.php'],
    'min-explicitness-invalid-101' => ['--min-explicitness=101', 'test-fixtures/env-access.php'],
    'min-explicitness-invalid-exponent' => ['--min-explicitness=1e2', 'test-fixtures/env-access.php'],
    'min-explicitness-invalid-empty' => ['--min-explicitness=', 'test-fixtures/env-access.php'],
    'min-explicitness-invalid-leading-point' => ['--min-explicitness=.5', 'test-fixtures/env-access.php'],
    'min-explicitness-invalid-trailing-point' => ['--min-explicitness=50.', 'test-fixtures/env-access.php'],
    'min-explicitness-invalid-trailing-newline' => ["--min-explicitness=5\n", 'test-fixtures/env-access.php'],
];
