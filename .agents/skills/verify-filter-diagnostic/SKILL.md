---
name: verify-filter-diagnostic
description: Check invalid filter-pattern diagnostics under caller error handlers. Use when changing FileFilter regex validation, warning capture, or embedding behavior.
---

# Verify filter diagnostics

**Property:** An invalid include or exclude pattern returns a nonempty diagnostic about that pattern, not an older unrelated warning, even when the caller consumes PHP warnings.

**Harm:** An embedding application can show an empty or misleading configuration error.

From the repository root, with Composer dev dependencies installed, run:

```sh
php tools/state-machine-checks/filter-diagnostic.php
```

The script checks two invalid patterns, both flags and three warning histories: 12 cases. Expect `PASS: 12 invalid-pattern diagnostics`. It runs in its own PHP process because it clears the process's last-error slot. Other handlers and patterns are outside the bound. This is a generated real-code check, not a PHP proof. `tests/FileFilterTest.php` also checks detailed reasons through the real application, preservation of the last-error slot, and restoration of consuming and throwing handlers.

Before the fix for [#204](https://github.com/jonbaldie/explicitness-checker/issues/204), eight cases failed. A consuming handler produced an empty reason or an older unrelated warning. `compileError()` now captures its own regex warning and restores the caller's handler.

Coverage: `src/Cli/FileFilter.php::error()`, `firstCompileError()` and `compileError()`; generator: `tools/state-machine-checks/filter-diagnostic.php` and `tools/state-machine-checks/bootstrap.php`. Last synced to source commit `f591eae1079a8754a11b35bfff140c5432483a60`. Inspect changes since that commit and uncommitted changes. Move the sync commit only after confirming the property and cases still describe the code at the new commit.

On failure, compare the warning history and actual message. Report counterexamples; do not weaken the property to make it pass. To test the check itself, make `compileError()` return an empty warning in a disposable worktree. The check must fail. Restore the source and confirm all 12 cases pass. `--ordinary-only` selects the four cases without a caller-installed handler for diagnosis. Do not change product code unless asked.
