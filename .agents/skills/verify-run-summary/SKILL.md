---
name: verify-run-summary
description: Check bounded run-summary counts, minimum thresholds and exit codes. Use when changing CLI aggregation, severity, parse failures, or explicitness thresholds.
---

# Verify run summary

**Property:** Counts and percentage reflect checked functions; a met minimum waives violations, but a parse failure still forces at least exit 2, and unreadable files change neither percentage nor exit.

**Harm:** A wrong exit code can make CI pass a failed analysis or fail an acceptable run.

From the repository root, with Composer dev dependencies installed, run:

```sh
php tools/state-machine-checks/run-summary.php
```

The check supplies the real `RunSummary` with all 1,360 combinations of up to three functions with severity 0–3, no minimum or integer minimum 0/50/100, and parse-failure and unreadable-file flags. It checks counts, tenths, minimum and exit. It constructs result objects rather than invoking the CLI. Decimal minima, unreadable directories and other categories are outside the bound. No PHP code-level verifier is used; this is not a proof for arbitrary runs. Confirmed with PHP 8.4.1 and the locked dependencies.

Coverage: `src/Cli/RunSummary.php`, `src/Cli/Severity.php`, `src/Cli/ExplicitnessMinimum.php`, `src/Cli/FileCheckResult.php`, `src/Cli/UncheckedInput.php`; generator: `tools/state-machine-checks/run-summary.php` and `tools/state-machine-checks/bootstrap.php`. Last synced to source commit `0e615ecb7af7278d9c43bb9d78711653eae72298`. Inspect changes since that commit and uncommitted changes. Move the sync commit only after confirming the property and cases still describe the code at the new commit.

On failure, inspect the severity list, threshold and input-failure flags; distinguish a desired contract change from a regression. Report a real counterexample rather than weakening the check. To test the check itself, in a disposable worktree make `RunSummary::raisesExitCode()` always return false; the parse-failure cases must fail. Restore and rerun.
