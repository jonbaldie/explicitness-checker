# Exploratory testing: clock, mutation, and file selection (2026-11-05)

This pass tested `origin/main` at `59461121dbbf76fd07bd84af978fca4515996921`. It found no confirmed bugs or unresolved product observations. The [previous pass](2026-10-28-output-severity-and-baselines.md) covered output, severity, and PHPStan baselines.

## Setup and evidence

The CLI ran on PHP 8.5.4 in a separate worktree. `composer install --no-interaction --prefer-dist --no-progress` installed the versions in `composer.lock`. Composer home, cache, temporary files, dependencies, and PHPStan's temporary cache were inside the worktree. The commands ran in the foreground. No server, browser, container, image, volume, or background process was started. An empty resource inventory and a cleanup trap were in place before installation and exploration.

The [journey script](evidence/2026-11-05/journeys.sh) creates isolated fixtures under `.run/journeys` and invokes the public CLI. The [transcript](evidence/2026-11-05/journeys.log) records the commands, findings, exit codes, and fixture hashes before and after analysis. A second run from recreated fixtures produced identical output. To replay it, install the locked dependencies and run `sh docs/exploratory-testing/evidence/2026-11-05/journeys.sh` from a worktree. The script replaces `.run/journeys`.

## Journey 1: distinguish fixed dates from clock reads

A developer checks date construction in strict mode. The README says complete fixed dates and formats with reset directives do not read the clock, while relative dates and formats that leave fields to now do. The CLI reported `relative_date` and `partial_format` as critical and exited 3. It did not report `fixed_date` or `fixed_format`. Both results recurred on replay.

## Journey 2: detect mutation of caller-owned data

A developer checks a cart operation in default mode. The README distinguishes copied arrays and top-level writes to shallow clones from writes to shared objects and by-reference arguments. The CLI reported `shared_object` and `reference_sort` as serious and exited 2. It did not report `copied_array` or `shallow_clone`. Both results recurred on replay.

## Journey 3: select application files

A developer checks an application while excluding dependencies and generated code. The ordinary run reported the generated file and cart findings, but not the file under `vendor`. Adding `--exclude=generated` removed the generated finding and retained the cart findings. A run with two include patterns and one exclude pattern, in strict mode, reported the two clock findings and excluded the cart file. An invalid include pattern produced a diagnostic and exit 2 before analysis. Results recurred on replay. Fixture hashes matched before and after the commands.

The first observation of the combined patterns used default mode and returned no findings. That result did not prove which file was selected because the clock findings require strict mode. The transcript includes that observation and the strict-mode run, in which the selected file has visible findings. This was an observation problem, not a product failure.

## Disposition and limits

No issue was filed. The current issue list, labels, comments, open PRs, and the prior rejection in [#12](https://github.com/jonbaldie/explicitness-checker/issues/12) were reviewed before the pass. These examples do not prove completeness of date inference, object provenance, or file selection. The PHPStan extension, other PHP versions, framework integrations, and mutation testing were not explored in this pass.

PHPUnit passed 1,149 tests and 3,472 assertions with a 1 GB PHP memory limit. PHPStan, PHPMD, standards, and the checker on its own source passed. The full Infection suite was not run locally. The PR workflow skips mutation testing when no PHP source files change.
