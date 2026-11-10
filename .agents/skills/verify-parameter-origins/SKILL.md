---
name: verify-parameter-origins
description: Check bounded local binding histories for argument mutation. Use when changing parameter origins, local assignments, clones, unsets, or argument-mutation detection.
---

# Verify parameter origins

**Property:** A property write through a local last assigned the parameter is reported; reassignment to a fresh object or top-level clone, or unset, removes that direct origin.

**Harm:** Lost or stale origins hide argument mutation or report it against the wrong argument.

From the repository root, with Composer dev dependencies installed, run:

```sh
php tools/state-machine-checks/parameter-origins.php
```

The check generates all 341 histories of length 0–4 over share, fresh object, clone and unset, then checks a property write using the real `SourceChecker`. It does not execute the generated PHP. It does not cover nested clone selections, references, arrays, branches or arbitrary PHP. No PHP code-level verifier is used; this is exhaustive only within the stated bound. Confirmed with PHP 8.4.1 and the locked dependencies.

Coverage: `src/Scope/ValueBindings.php`, `src/Scope/ValueOrigins.php`, `src/Scope/ValueSelections.php`, `src/Scope/Denotation.php`, `src/Detect/ArgumentMutationDetector.php`; generator: `tools/state-machine-checks/parameter-origins.php` and `tools/state-machine-checks/bootstrap.php`. Last synced to source commit `0e615ecb7af7278d9c43bb9d78711653eae72298`. Inspect changes since that commit and uncommitted changes. Move the sync commit only after confirming the property and cases still describe the code at the new commit.

On failure, compare the action sequence and resulting finding against the intended PHP and checker behavior. Report a real counterexample rather than weakening the check. To test the check itself, in a disposable worktree stop `ValueBindings::leave()` applying an `Expr\Assign`; the check must fail. Restore and rerun.
