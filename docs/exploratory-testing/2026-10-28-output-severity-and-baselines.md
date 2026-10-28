# Exploratory testing: output, severity, and PHPStan baselines (2026-10-28)

This pass tested `main` at `5d6c9dbf930fbad2f15e37e4c1a1066ad0fc4bb2`. It found no confirmed product bugs. The previous [transitive, shell, and gate pass](2026-10-24-transitive-shell-and-gate.md) covered different scenarios.

## Setup and evidence

PHP 8.5.4 and the dependencies in `composer.lock` were used in a worktree. `composer install --no-interaction --prefer-dist` installed the locked versions. Composer home, cache, temporary files, and PHPStan's result cache were local to the worktree. [The transcript](evidence/2026-10-28/journeys.log) records commands, output, and exit codes. The [output fixture](evidence/2026-10-28/output.php), [input fixture](evidence/2026-10-28/input.php), and [generated baseline](evidence/2026-10-28/baseline.neon) preserve the tested data. The last line of the input fixture was added after the baseline was generated.

The run registered an empty resource inventory before the journeys. It started no servers, background processes, browser sessions, containers, images, or volumes. It did not set an exit trap, so early-exit cleanup was not automatic. All commands ran in the foreground.

## Journey 1: distinguish output from returned text

A developer checks a file with a pure function, `echo`, `print_r($x, true)`, and `print_r($x, false)`. The README says output is reported in strict mode and `print_r` with `return: true` is not reported. Default mode reported no findings and exited 0. Strict mode reported `echo` and the `false` call, but not the pure function or the `true` call. It exited 1 for minor findings. The checker did not alter the fixtures.

## Journey 2: use severity and a percentage gate

A developer checks functions that read `$_GET` and `$_ENV`. Default mode reported the first as serious and the second as critical, and exited 3. With `--min-explicitness=0`, the same findings remained visible and the exit became 0. The summary showed 0 of 2 explicit function-likes and the actual exit code. This agrees with the documented gate, which waives finding-based exit codes when the minimum is met.

## Journey 3: baseline existing PHPStan findings

A developer enables the extension with strict mode and generates a baseline before adding a new violation. The first analysis reported four errors with the expected `explicitness.superglobal` and `explicitness.standardOutput` identifiers. Generating the baseline at its final location retained the identifiers. Including it produced a clean analysis. Adding a function that reads `$_POST` produced exactly one new `explicitness.superglobal` error and exit 1. The baseline therefore did not suppress the new finding.

The first attempt moved a baseline generated in the repository root into the fixture directory. PHPStan then rejected its relative paths. That was a driver error, not a product finding. Generating the baseline directly at its final location corrected the paths. The original failure and corrected runs are both in the transcript.

## Disposition and limits

No confirmed or unresolved product bugs were found. No issue was filed. The output return-flag, highest-severity exit, gate waiver, baseline identifiers, and new-violation candidates behaved as documented in these examples. Existing and closed issues were reviewed before disposition. In particular, [#187](https://github.com/jonbaldie/explicitness-checker/issues/187) covers a prior PHP 8.5 closure diagnostic and was not retested here.

PHPUnit passed 1,146 tests and 3,463 assertions with a 1 GB PHP memory limit. PHPStan, standards, and the checker on its own source passed. The full mutation suite, PHP versions other than 8.5, framework integrations, and broader output functions were not exercised. These examples do not establish completeness of the strict catalogue or baseline behavior for all PHPStan configurations.

The baseline path incident is a usability observation about moving a generated PHPStan baseline, not a reported checker defect. Generate it at its intended location when using relative paths.
