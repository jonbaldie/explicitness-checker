---
name: verify-file-discovery
description: Check bounded directory traversal, filtering and sorted results. Use when changing DirectoryWalk, PhpFileFinder, or file filtering.
---

# Verify file discovery

**Property:** Every matching PHP file in the tested tree is returned once in sorted order, including files in a nested directory, while the selected exclusions are respected.

**Harm:** Skipping a child or failing to resume its parent silently omits files from analysis.

From the repository root, with Composer dev dependencies installed, run:

```sh
php tools/state-machine-checks/file-discovery.php
```

The check makes an isolated temporary tree and runs the real `PhpFileFinder` over every subset of five file paths with four filters: 128 combinations. It removes the tree afterward. It covers uppercase `.PHP`, a non-PHP leaf and a vendor subtree. It does not cover unreadable directories, symbolic links, races or arbitrary patterns and trees. No PHP code-level verifier is used; this is exhaustive only for the stated tree and filters. Confirmed with PHP 8.4.1 and the locked dependencies.

Coverage: `src/Cli/DirectoryWalk.php`, `src/Cli/PhpFileFinder.php`, `src/Cli/FileFilter.php`, `src/Cli/DiscoveredFiles.php`; generator: `tools/state-machine-checks/file-discovery.php` and `tools/state-machine-checks/bootstrap.php`. Last synced to source commit `0e615ecb7af7278d9c43bb9d78711653eae72298`. Inspect changes since that commit and uncommitted changes. Move the sync commit only after confirming the property and cases still describe the code at the new commit.

On failure, inspect the file mask, filter and paths; check the host filesystem behavior before calling it a product bug. Report a real counterexample rather than weakening the check. To test the check itself, in a disposable worktree replace the recursive yield in `DirectoryWalk::events()` with an empty yield; the check must fail. Restore and rerun.
