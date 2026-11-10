---
name: verify-global-aliases
description: Check bounded histories of references to GLOBALS. Use when changing reference assignment, local unset, alias tracking, or GLOBALS findings.
---

# Verify GLOBALS aliases

**Property:** A write through a local reference is attributed to its current `$GLOBALS` target; after `unset`, a local write is not attributed to the old target.

**Harm:** Stale or missing alias state misreports shared-state writes.

From the repository root, with Composer dev dependencies installed, run:

```sh
php tools/state-machine-checks/global-aliases.php
```

The check generates every one of 341 histories of length 0–4 over binding to either of two global entries, unset, and ordinary write. It compares the real `SourceChecker` output with the first occurrence of each expected target. It does not execute the generated PHP or cover branches, dynamic names, references to other locals or arbitrary PHP. No PHP code-level verifier is used; this is exhaustive only within the stated bound. Confirmed with PHP 8.4.1 and the locked dependencies.

Coverage: `src/Scope/ReferenceAliases.php`, `src/Scope/JoinMap.php`, `src/Scope/Denotation.php`, `src/Detect/GlobalsArrayDetector.php`; generator: `tools/state-machine-checks/global-aliases.php` and `tools/state-machine-checks/bootstrap.php`. Last synced to source commit `0e615ecb7af7278d9c43bb9d78711653eae72298`. Inspect changes since that commit and uncommitted changes. Move the sync commit only after confirming the property and cases still describe the code at the new commit.

On failure, trace the named history through reference assignment, unset and the reported write. Report a real counterexample rather than weakening the check. To test the check itself, in a disposable worktree replace the target stored by `ReferenceAliases::assignReference()` with `null`; the check must fail. Restore and rerun.
