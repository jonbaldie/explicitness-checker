---
name: verify-branch-joins
description: Check conservative GLOBALS-alias joins across branches and loops. Use when changing JoinMap fork, restore, merge, or reference-alias handling.
---

# Verify branch and loop joins

**Property:** After the tested `if` alternatives, every target from the saved state or either arm remains a possible write target; a zero-iteration foreach retains its saved target.

**Harm:** Losing one alternative hides a possible write after a branch or loop.

From the repository root, with Composer dev dependencies installed, run:

```sh
php tools/state-machine-checks/branch-joins.php
```

The check uses the real `SourceChecker` for all 48 combinations of three initial states and four operations in each of two arms, plus two zero-iteration foreach cases. It checks target sets, not order. The saved state is deliberately included even for `if/else`: this is the checker's conservative join, not an exact control-flow claim. It does not cover other fork kinds, nesting, value origins or fixed points. No PHP code-level verifier is used; this is exhaustive only over these cases. Confirmed with PHP 8.4.1 and the locked dependencies.

Coverage: `src/Scope/JoinMap.php`, `src/Scope/ReferenceAliases.php`, `src/Scope/Denotation.php`; generator: `tools/state-machine-checks/branch-joins.php` and `tools/state-machine-checks/bootstrap.php`. Last synced to source commit `0e615ecb7af7278d9c43bb9d78711653eae72298`. Inspect changes since that commit and uncommitted changes. Move the sync commit only after confirming the property and cases still describe the code at the new commit.

On failure, inspect initial and arm bindings and distinguish an intended change in approximation from a lost target. Report a real counterexample rather than weakening the check. To test the check itself, in a disposable worktree omit the alias-outcome merge in `JoinMap::leave()`; the check must fail. Restore and rerun.
