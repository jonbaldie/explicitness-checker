# Exploratory testing: transitive calls, shell exemptions and the percentage gate (2026-10-24)

AFK pass against `main` at `1cd19f9cd51bc1b2cbadadbdef8d5bf6042e4e82`. One confirmed bug: [#187 — PHP 8.5 closure/arrow deprecations](https://github.com/jonbaldie/explicitness-checker/issues/187). No product fixes were made.

The previous pass, [2026-10-10](2026-10-10-pipe-bindings-and-catalogue.md), covered pipe calls, shared-state writes and catalogue direction. This pass instead drove the CLI's newer transitive analysis, the shell-exemption guide, and file selection with the percentage gate.

## Setup and evidence

- PHP 8.5.9, Xdebug 3.5.3, php-parser 5.9.0 and PHPStan 2.2.14. PHP 8.3.33 was available for comparison and baseline checks.
- `composer install --no-interaction --prefer-dist` succeeded against `composer.lock`. `vendor/` is a real workspace directory and contained no symlinks. Dependencies and build outputs were not shared through another checkout.
- Fixtures and PHPStan caches were isolated under workspace `build/`; the supplied `TMPDIR` was preserved. Composer used its configured shared download cache. Initial artifacts used the scratch label `build/exploratory-2026-10-14/`; the actual run date from the host was October 24. Replay artifacts use `build/exploratory-2026-10-24/`.
- [Replay transcript](evidence/2026-10-24/journeys.log): commands, stdout/stderr and observed exits, with the repository path replaced by `<repo>`. This is a clean repeat with `XDEBUG_MODE=off`, not the initial observation.
- [Baseline checks](evidence/2026-10-24/checks.log) and [minimal diagnostic without Xdebug traces](evidence/2026-10-24/closure-no-xdebug.log).
- Full initial diagnostics, including the 30 MB PHPUnit failure log and Xdebug traces, remain locally in `build/exploratory-2026-10-14/`. The committed check log includes the failure reason, not that entire trace.

No Docker command was needed. This run created zero containers, images or volumes, so none required removal. The three pre-existing untracked exploratory reports were not changed or committed.

## 1. Follow actions through selected declarations

**Goal:** callers acquire the input/output direction of reachable actions only with `--transitive`, and the graph respects selected files and the active strict/props modes. Expectations came from the README's transitive contract.

**Ordinary path:** a three-function input chain; pure argument-bearing mutual recursion; a cycle containing a superglobal read; inherited `$this` calls and `parent::read()`. The direct run reported 3 of 16 function-likes. Transitive mode reported 10 of 16, with 6 explicit (37.5%). The action cycle terminated. Pure recursion remained clean.

**Variations:** imported function aliases, grouped imports, fully qualified names, `namespace\sub`, and namespaced-to-global fallback all propagated. All 9 declarations were implicit when their targets were selected. Excluding the two imported targets made their three callers clean: 3 of 7 explicit (42.8%). Nested arrows acquired their own findings without tainting the enclosing function; first-class callable storage stayed clean.

A service fixture checked mode changes: default transitive mode reported a non-constructor `$this` write and its caller, leaving 5 of 7 explicit. `--strict --props --transitive` additionally reported constructor initialization, property readers and a clock chain, leaving 0 of 7 explicit. A propagated clock caller was Serious while the direct clock action was Critical; the run exited 3, as documented.

**Boundary observation:** a class using a trait to override an impure parent method was still linked to the parent method. The trait implementation returned `$x + 1`, but the child caller acquired a read finding. Traits/adaptations are expressly outside the documented graph; this was rejected as a contract violation, not filed as a new bug. This pass did not establish runtime dispatch correctness.

## 2. Exempt the shell without exempting the core

**Goal:** excluding `src/Command` leaves `src/Billing/Command` checked, and PHPStan identifier/path ignores keep other PHPStan rules active. Expectations came from [Functional core, imperative shell](../functional-core-imperative-shell.md).

**CLI ordinary path:** a shell read a superglobal, a core command-message function read another, and a calculation was pure. Default `vendor/` exclusion held: all-app strict analysis checked 3 function-likes, not the vendor fixture, and found 2 implicit ones.

**Variations:** `--exclude=src/Command` left the core command-message finding (1 of 2 explicit); `--exclude=Command` removed both command directories (1 of 1 explicit). Absolute, relative and `./`-prefixed analysis roots agreed. Selecting the excluded directory directly found no files. Selecting an individual PHP file bypassed directory exclusions and checked it; inspection of `PhpFileFinder` confirmed this is an intentional file-target exception, so it was not filed.

**PHPStan ordinary path:** at level 5, a shell with `$this->value` and `Clock::get()` reported three extension errors (property input plus static-call input/output). Scoped `explicitness.objectProperty` and `explicitness.staticCall` ignores made the run clean. Adding `$this->missing()` then produced exactly one `method.notFound` error. The exemptions had not switched off ordinary PHPStan checks. Local config and result caches stayed under `build/`.

## 3. Adopt a gate, break a file, then correct selection

**Goal:** the minimum compares the actual explicit fraction rather than the displayed rounded percentage, filters select the intended functions, and the gate never waives a parse failure.

**Ordinary path:** two pure declarations and one superglobal reader showed 66.6%. Minimum `66.66` exited 0; `66.67` exited 2. Minimum `0` waived findings, and `100` failed.

**Variations:** adding an unparseable fourth file kept the checked total at 3 and forced exit 2 even with minimum 0. Excluding the broken file restored exit 0. Including only the pure file checked 2 of 2 and passed minimum 100. An invalid include regex stopped before analysis with exit 2 and a regex diagnostic. A pattern selecting nothing exited 0 with `No PHP files found`; it did not print a percentage. No candidate from this journey was confirmed as a bug.

## Confirmed finding: clean closures emit PHP 8.5 deprecations

Filed as [#187](https://github.com/jonbaldie/explicitness-checker/issues/187), labelled `bug` and `needs-triage`.

**Impact:** a clean CLI run emits an internal deprecation and, when enabled, Xdebug's stack trace, yet exits 0. The ordinary first graph fixture exposed it before any PHP diagnostic settings were changed.

**Minimal starting file:**

```php
<?php
function wrap($x) { return fn($y) => $x + $y; }
```

**Replay:** save as `clean.php`, then run twice:

```sh
php -d error_reporting=32767 -d display_errors=1 bin/explicitness-checker clean.php
```

Expected: `No implicit inputs or outputs found.`, exit 0, without internal diagnostics.

Actual: `Using null as an array offset is deprecated, use an empty string instead` at `src/Scope/FunctionLikeCollector.php:104`, followed by the clean result, exit 0. Both unmodified-configuration minimal runs reproduced it. A traditional closure did too. Repeating with `XDEBUG_MODE=off` retained the deprecation; PHP 8.3 produced only the clean result.

Source inspection after reproduction found `className()` indexes the empty class stack with `array_key_last(...)`, which returns null. This explains the observation; no instrumentation or source fix was applied. Explicit `error_reporting` flags above make the diagnostic prerequisite portable, but were not needed in the original installation (`error_reporting=30719`, `display_errors=1`).

## Candidate disposition and limitations

- **Confirmed:** closure/arrow deprecation on PHP 8.5 — #187.
- **Rejected:** graph nontermination, import-resolution misses, mode-insensitive propagation, gate rounding errors, gate waiving parse failures, broadening of precise shell excludes, and PHPStan exemptions suppressing unrelated errors. The exercised cases behaved as described above.
- **Rejected by documented scope:** trait override resolution and directory exclusions on an explicit single-file target. Neither is evidence that the checker proves purity.
- **Environment limitation:** the initial unmodified PHP 8.5 `composer test` run exhausted its 128 MB memory limit in PHPStan's parser, after deprecations and some failures had already appeared. It is not a passing baseline. The full original failure log is preserved locally. With PHP 8.3 and a 1 GB limit, PHPUnit passed: 1,082 tests and 3,385 assertions. PHPStan, PHPMD, standards and self-explicitness checks also passed under PHP 8.3. No dependency or product-source change was made to obtain that comparison.
- **Not exercised:** dynamic callback dataflow, arbitrary object dispatch, frameworks or fresh Composer consumer installation, PHPStan pipe syntax, unreadable-file permissions, other PHP versions, and full local Infection mutation testing. Documentation-only PR mutation jobs use the repository's no-source-change skip. The pass made no whole-program or cross-version correctness claim.
- **Usability:** selecting no files exits 0 without a gate percentage; explicit single-file selection disregards directory excludes. Both are observable selection boundaries worth checking when wiring CI, not newly filed defects.

## Replay and cleanup

From the repository root, with installed locked dependencies, Python 3 and PHP on PATH, use a fresh `build/exploratory-2026-10-24/` directory and run:

```sh
XDEBUG_MODE=off python3 docs/exploratory-testing/evidence/2026-10-24/probe.py
XDEBUG_MODE=off python3 docs/exploratory-testing/evidence/2026-10-24/followup.py
XDEBUG_MODE=off python3 docs/exploratory-testing/evidence/2026-10-24/phpstan-probe.py
```

These drivers write fixtures/config/cache/logs only below that build directory and print observations; they are not assertion-based regression tests. Run them in order. Before a second complete replay, remove only that dedicated build directory: the first driver deliberately adds a broken file late in its journey. The driver initially had a Python invalid-escape warning; raw fixture strings removed it before the preserved replay.

Run-created scratch fixtures and PHPStan caches were removed after evidence capture; run logs and the locked workspace dependencies were retained. No server or container remained running.
