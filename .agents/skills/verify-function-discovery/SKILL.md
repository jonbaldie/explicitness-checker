---
name: verify-function-discovery
description: Check function-like names and class-context restoration with bounded generated source. Use when changing function discovery, namespace or class naming, or finder traversal.
---

# Verify function discovery

**Property:** After leaving a nested anonymous class, later methods are named for their own enclosing class, in source order and namespace.

**Harm:** A leaked class context gives users and PHPStan the wrong function name.

From the repository root, with Composer dev dependencies installed, run:

```sh
php tools/state-machine-checks/function-discovery.php
```

A pass checks all 12 combinations of three namespaces, two class orders and two inner method names through the real `SourceChecker`. It checks five reported names per source. It does not cover every AST shape, closures, hooked properties, PHPStan parser variants or arbitrary nesting. No PHP code-level verifier is used; this is exhaustive only over that finite set, not a proof for all PHP programs. Confirmed with PHP 8.4.1 and the locked dependencies.

Coverage: `src/Scope/FunctionLikeCollector.php`, `src/Scope/FunctionLikeFinder.php`, `src/Scope/FunctionLikeNames.php`, `src/SourceChecker.php`; generator: `tools/state-machine-checks/function-discovery.php` and `tools/state-machine-checks/bootstrap.php`. Last synced to source commit `0e615ecb7af7278d9c43bb9d78711653eae72298`. Inspect changes since that commit and uncommitted changes. Move the sync commit only after confirming the property and cases still describe the code at the new commit.

On failure, read the generated source and the expected and actual names. Check whether the behavior or the oracle is wrong; report a real counterexample rather than weakening the check. To validate the check itself, in a disposable worktree remove the class-stack pop in `FunctionLikeCollector::leave()`: it must fail. Restore the source and confirm it passes.
