# Exploratory testing: CLI and PHPStan extension — 2026-09-20

An exploratory pass over `bin/explicitness-checker` and the PHPStan rule,
driving both through the interfaces a user actually touches: the shell command
on a scratch project, and `vendor/bin/phpstan analyse` against a `phpstan.neon`
that includes `extension.neon`.

## Environment

| | |
|---|---|
| Commit | `cab2154` (`main`, clean tree) |
| PHP | 8.5.9 (Homebrew), Xdebug 3.5.3 present; runs repeated with `-d xdebug.mode=off` |
| PHPStan | 2.2.14 (`vendor/bin/phpstan`) |
| php-parser | v5.9.0 |
| Scratch projects | `/tmp/ec-explore/{sample,detect,modern,scope,readme,iso1,iso2}` |

Every reproducer in this report is self-contained: each linked issue carries the
shell commands that create its fixture from scratch, so nothing depends on the
scratch directories surviving. Those directories were removed at the end of the
pass.

## Journeys exercised

### 1. Run the checker on a project and gate CI on the exit code

Ordinary path: a scratch project with `src/`, `tests/` and a `vendor/` holding a
third-party file. Default mode reported the global-variable and superglobal
findings and exited 2; `vendor/` was excluded without being asked for, as
documented. `--strict` added the `date()` and `echo` findings and raised the
exit to 3; `--props` added `$this->` reads and stayed at 2; `--verbose` printed
a per-file, per-function trace to stdout. A file whose worst finding is `echo`
exits 1, so all four documented exit codes were observed.

Variations: a single file path instead of a directory (works); a missing path
(usage, exit 2); a nonexistent path (`Path not found`, exit 2); a file with a
syntax error (`Parse error in ...`, analysis continues on the other files).

This journey produced [#23](https://github.com/jonbaldie/explicitness-checker/issues/23)
and [#24](https://github.com/jonbaldie/explicitness-checker/issues/24).

### 2. Narrow the run with the filtering flags

Ordinary path: `--exclude`, `--include-pattern` and `--exclude-pattern` in both
the `=value` and space-separated forms. Repeated `--exclude` accumulates on top
of the default `vendor`. The README's own example pattern
`--include-pattern="src/.*\.php$"` works — `FileFilter::delimit()` escapes bare
`/` correctly, so the regression behind
[#4](https://github.com/jonbaldie/explicitness-checker/issues/4) has not
returned. Nested directory excludes (`--exclude='gen/nested'`) work.

Variations: malformed regexes, repeated pattern flags, mistyped flag names.

This journey produced [#22](https://github.com/jonbaldie/explicitness-checker/issues/22)
and [#26](https://github.com/jonbaldie/explicitness-checker/issues/26).

### 3. Install the PHPStan extension and check CLI/rule parity

Ordinary path: a `phpstan.neon` with `includes: [extension.neon]` and
`explicitness: {strict: true, props: true}`, run against a fixture exercising
thirteen detection categories (superglobal write, `$GLOBALS` read/write,
`self::$prop` and `Class::$prop`, `file_get_contents`, `header`, `random_int`,
`error_log`, `session_start`, dynamic `$GLOBALS[$k]`, `\time()`, `print`,
`var_dump`).

**Parity held exactly**: every row the CLI reported appeared as a PHPStan error
at the same line, under the same function name, with the same message text and a
matching `explicitness.*` identifier. This is the strongest promise the README
makes about the extension and it holds.

Variations: omitting the `explicitness` section entirely (defaults to
`strict: false, props: false`, four findings); setting only `strict` without
`props` (accepted, `staticProperty`/`objectProperty` correctly absent).

This journey produced [#27](https://github.com/jonbaldie/explicitness-checker/issues/27).

### 4. Check the README's claims against the tool

The scope rules in "Which functions are checked, and how they're named" were
exercised directly, in a file with no namespace: a closure declaring
`global $x` inside a function (reported as its own `{closure}` row, not leaking
into the enclosing function), a conditionally declared function, an
anonymous-class method (`class@anonymous::send`), an arrow function reading
`$GLOBALS`. **All four behaved exactly as documented.** The headline example and
the "Example Output" block did not.

This journey produced [#25](https://github.com/jonbaldie/explicitness-checker/issues/25).

## Confirmed findings

| # | Issue | Severity | Summary |
|---|---|---|---|
| 1 | [#22](https://github.com/jonbaldie/explicitness-checker/issues/22) | High | Invalid filter regex: PHP warnings per file, zero files analysed, **exit 0** |
| 2 | [#23](https://github.com/jonbaldie/explicitness-checker/issues/23) | Medium | Unknown options silently ignored; a typo'd `--strict` turns the check off and exits 0 |
| 3 | [#24](https://github.com/jonbaldie/explicitness-checker/issues/24) | Medium | Report prints `basename()` only; same-named files give identical, unnavigable rows |
| 4 | [#25](https://github.com/jonbaldie/explicitness-checker/issues/25) | Medium | README headline example reports nothing; "Example Output" doesn't match the CLI |
| 5 | [#26](https://github.com/jonbaldie/explicitness-checker/issues/26) | Low | Repeated `--exclude-pattern`/`--include-pattern` silently drops all but the last |
| 6 | [#27](https://github.com/jonbaldie/explicitness-checker/issues/27) | Low | PHP 8.4 property hooks are named `{closure}` |

Findings 1, 2 and 5 are one family: **the CLI has no way to tell a user it did
not understand them.** Every malformed input — a bad regex, a mistyped flag, a
repeated pattern option — is absorbed silently, and in the first two cases the
result is a green exit code on a check that ran on nothing. For a tool whose
entire contract is its exit code, that is the sharpest edge found in this pass.

## Rejected candidates

- **PHPStan reports nothing for a file containing an `enum`.** Reproduced, then
  rejected: plain `vendor/bin/phpstan` without this extension loaded fails the
  same file with `phpstan.parse` syntax errors, and setting `phpVersion: 80400`
  makes both the errors and the expected `explicitness.*` findings appear. This
  is PHPStan's own `phpVersion` default resolving low in a scratch directory
  with no `composer.json`, not a defect in the rule.
- **Dynamic calls to impure functions are not detected** (`$f = 'time'; $f();`).
  Real, but outside what any AST-only analyser can promise, and not claimed
  anywhere in the README.
- **Reading an undeclared local (`return $undeclared + 1;`) is not flagged.**
  Correct: without a `global` declaration PHP treats it as a local, and the tool
  models that faithfully. This is the same PHP semantics that make the README's
  headline example wrong (finding 4).
- **`--help` prints usage and exits 2.** `--help` is not a documented flag, so
  this is just the no-path branch. Noted below as usability, not filed.

## Unresolved

- **A file that fails to parse leaves the exit code untouched.** `Parse error in
  ./broken/Bad.php` is printed and analysis continues; a run over a directory
  whose only file is unparseable prints `No implicit inputs or outputs found.`
  and exits 0. Best-effort continuation is defensible, but it means a project
  using syntax the pinned php-parser cannot read is silently unchecked and CI
  stays green. Whether the exit code should reflect it is a product decision,
  not a bug I can confirm against a documented promise.

## Usability observations

Observations from the attempted journeys, kept separate from the suggestions
that follow them:

- **The usage message exits 2**, which collides with the documented "Serious
  violations found". A wrapper script that branches on the exit code cannot
  distinguish "you called me wrong" from "your code has serious violations".
  *Suggestion:* reserve a distinct code for usage errors, and add a real
  `--help` that exits 0.
- **`Analyzing...` no longer names the path.** The README's example shows
  `Analyzing ./path/to/your/project`, which is the more useful form when the
  output is scrolled back through in a CI log.
- **With `--verbose`, the `Analyzing...` banner prints after the whole verbose
  trace**, so the banner reads as a footer rather than a header.
- **The results table has no width limit.** Running against this repo's own
  `src/` with `--strict --props` produced a 55-row table whose widest line is
  412 characters, because one function's combined findings are that long. It is
  unreadable in a normal terminal. *Suggestion:* wrap the two findings columns,
  or offer a one-finding-per-line format for wide output.
- **`Summary:` lists all three severities with their exit codes even when the
  count is 0**, which reads oddly on a clean-ish run (`Minor violations: 0 (exit
  code 1)` invites the reader to think 1 is the exit code).

## Limitations of this pass

- Xdebug is loaded in this environment and its `develop` mode adds a stack trace
  to every PHP warning. Finding 1 was therefore re-verified with
  `-d xdebug.mode=off` to establish that the warnings, the empty analysis and
  the exit code 0 are all present without it; only the trace volume is
  environment-specific.
- The PHPStan journey ran the extension from a `phpstan.neon` with an explicit
  `includes:`. The `phpstan/extension-installer` path described in the README
  was read (`composer.json` `type: phpstan-extension` plus
  `extra.phpstan.includes`) but not installed and exercised end to end.
- Detection breadth was checked against hand-written fixtures covering the
  documented categories, not against a large real-world codebase. No claim is
  made here about false-negative rates on production code.
- Not explored: the `--exclude` interaction with absolute versus relative paths,
  symlinked directories, files without a `.php` suffix, and non-UTF-8 sources.
