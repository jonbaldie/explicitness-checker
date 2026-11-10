---
name: verify-reflected-signatures
description: Check reflection-cache snapshots, negative entries and file handoff. Use when changing reflected by-reference parameters, cache keys, or FileChecker cache propagation.
---

# Verify reflected signatures

**Property:** Advancing a snapshot reflects each function name at most once regardless of spelling case, preserves old snapshots, caches missing names, and passes the snapshot to the next successful file.

**Harm:** Lost or stale signature state can change reference-access findings or repeat expensive reflection across files.

From the repository root, with Composer dev dependencies installed, run:

```sh
php tools/state-machine-checks/reflected-signatures.php
```

The check runs all 85 histories of length 0–3 over `sort`, `SORT`, `preg_match`, and one absent name using the real reflection-backed cache and the existing counting subclass. It also checks a two-file `FileChecker::after()` handoff. `sort` and `preg_match` must be present in PHP. Other functions, extensions, parse failures and arbitrary cache sizes are outside the bound. No PHP code-level verifier is used. Confirmed with PHP 8.4.1 and the locked dependencies.

Coverage: `src/Walk/ReflectedByReferenceParameters.php`, `src/Walk/ReflectedParameterCache.php`, `src/SourceChecker.php`, `src/Cli/FileChecker.php`; generator: `tools/state-machine-checks/reflected-signatures.php`, `tools/state-machine-checks/bootstrap.php`, and `tests/Support/CountingByReferenceParameters.php`. Last synced to source commit `0e615ecb7af7278d9c43bb9d78711653eae72298`. Inspect changes since that commit and uncommitted changes. Move the sync commit only after confirming the property and cases still describe the code at the new commit.

On failure, inspect the sequence, cache snapshot and running PHP's built-ins. Report a real counterexample rather than weakening the check. To test the check itself, in a disposable worktree disable the cache-hit return in `ReflectedByReferenceParameters::withFunction()`; the check must fail. Restore and rerun.
