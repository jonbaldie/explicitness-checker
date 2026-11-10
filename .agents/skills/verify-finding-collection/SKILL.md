---
name: verify-finding-collection
description: Check finding deduplication, first location and immutable snapshots. Use when changing FindingCollector, finding order, or input/output recording.
---

# Verify finding collection

**Property:** A collector retains the first occurrence of each description in encounter order and does not alter previous snapshots.

**Harm:** Replacing an occurrence can point users to the wrong line or change the report ordering.

From the repository root, with Composer dev dependencies installed, run:

```sh
php tools/state-machine-checks/finding-collection.php
```

The check sends all 341 sequences of length 0–4 over two input and two output descriptions to the real `FindingCollector`, checks every intermediate state and previous snapshot, and also checks repeated accesses through `SourceChecker`. It does not cover all detectors or descriptions; descriptions are the deduplication key. No PHP code-level verifier is used; this is exhaustive only within the stated bound. Confirmed with PHP 8.4.1 and the locked dependencies.

Coverage: `src/FindingCollector.php`, `src/Finding.php`, `src/Analyser.php`; generator: `tools/state-machine-checks/finding-collection.php` and `tools/state-machine-checks/bootstrap.php`. Last synced to source commit `0e615ecb7af7278d9c43bb9d78711653eae72298`. Inspect changes since that commit and uncommitted changes. Move the sync commit only after confirming the property and cases still describe the code at the new commit.

On failure, inspect the event sequence, first line and snapshot; distinguish an intentional contract change from a regression. Report a real counterexample rather than weakening the check. To test the check itself, in a disposable worktree replace `??=` with `=` in `FindingCollector::record()`; the check must fail. Restore and rerun.
