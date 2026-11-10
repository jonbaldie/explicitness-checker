---
name: verify-project-propagation
description: Check directional project-call propagation against bounded graph reachability. Use when changing project indexing, call edges, or transitive input/output propagation.
---

# Verify project propagation

**Property:** Each selected function's input/output bits equal the union of direct bits reachable along its resolved calls, including cycles.

**Harm:** Stopping propagation early or losing an edge hides an action from its callers.

From the repository root, with Composer dev dependencies installed, run:

```sh
php tools/state-machine-checks/project-propagation.php
```

The check generates 768 PHP call graphs: every directed graph and seed combination for two functions; four selected three-function graphs with all seeds; and one four-function chain with all seeds. It parses and analyses each with the real checker and compares propagated bits against an independent graph search. Calls use literal arguments so the checker's separate zero-argument-call detector does not add findings. It does not cover dynamic calls, ambiguous declarations, methods, source selection or arbitrary graph sizes. No PHP code-level verifier is used; the result is bounded, not a proof for every project. Confirmed with PHP 8.4.1 and the locked dependencies.

Coverage: `src/Project/ProjectAnalysis.php`, `src/Project/ProjectCalls.php`, `src/Project/ProjectIndex.php`, `src/Project/CallEdge.php`; generator: `tools/state-machine-checks/project-propagation.php` and `tools/state-machine-checks/bootstrap.php`. Last synced to source commit `0e615ecb7af7278d9c43bb9d78711653eae72298`. Inspect changes since that commit and uncommitted changes. Move the sync commit only after confirming the property and cases still describe the code at the new commit.

On failure, decode the graph and seed bits in the counterexample and trace direct findings, edge resolution and reachability. Report a real counterexample rather than weakening the check. To test the check itself, in a disposable worktree stop `ProjectAnalysis::reach()` after one pass; the four-function chain must fail. Restore and rerun.
