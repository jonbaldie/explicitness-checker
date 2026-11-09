---
name: verify-walk-claims
description: "Check access-walk rule overlaps after changing claim rules or their helpers, or upgrading php-parser."
---

# Walk-claim disjointness

`AccessRules` walks a node with the first claim rule that returns a children list. The rules are meant to claim disjoint kinds of node, so the order of the rule list decides nothing (`src/Walk/AccessRules.php` says so, issue #57). When two rules claim the same node, the first wins silently. The checker can then report the wrong implicit reads and writes.

`tests/AccessRulesTest.php` checks node instances in `tests/Fixtures` and `src`. `tools/verify-walk-claims.php` also checks one fabricated instance of every concrete node class in the installed `nikic/php-parser`, plus selected call and variable shapes. This is a bounded check, not a proof for every possible node instance.

## Run it

From the repo root:

    php tools/verify-walk-claims.php

Exit 0 means no checked instance has multiple claimants. Exit 1 reports each overlap as `class [witness shape, mode]: rule, rule`, or names any rule that claimed no witness. A constructor or runtime error also fails the command.

Run it and review the claim predicates when you change any of these:

- `src/Walk/AccessRules.php`, `src/Walk/WriteRules.php`, `src/Walk/RuleChain.php`
- any claim rule's `children()`: `ReferenceAssignmentRule`, `AssignmentRule`, `CompoundAssignmentRule`, `IncrementDecrementRule`, `ForeachRule`, `CatchRule`, `UnsetRule`, `ByReferenceCallRule`, `LeafRule`, `ArrayIndexRule`, `ArrayItemRule`, `PropertyFetchRule`, `StaticDeclarationRule`
- a helper those predicates call: `src/GlobalsArray.php` (`isFetch`), `src/VariableName.php` (`of`), `src/Superglobals.php` (`includes`), `src/LiteralCallable.php` (`referenceForwardingFunction`, `directCall`, `callback`), `src/Scope/ScopeBoundary.php` (`opensScope`), `src/Walk/ReferenceDestructuring.php` (`bindsReference`)
- `src/Walk/ByReferenceParameters.php` or `src/Walk/ReflectedByReferenceParameters.php` (the harness pins its own catalogue, but a signature change here breaks the harness compile)

Rerun it after `composer update` changes `nikic/php-parser`. The check enumerates concrete node classes in the installed vendor tree. If a new class cannot be fabricated, update the factory before relying on the result.

## Read a failure

A violation line names the class, witness shape, mode, and claimants. Open the named rules and compare their predicates with the witness. Named shapes correspond to the expressions in `NodeWitnessFactory::shapeWitnesses()`. For a `fabricated` witness, inspect the factory or print the instance. Constructors can accept nodes that no PHP program would produce, so confirm that the witness is reachable before treating an overlap as a product bug.

The fixed by-reference catalogue recognizes `sort`, `preg_match`, and `strlen`. Other built-ins and extension-specific functions are outside the checked call shapes. If a failure depends on the catalogue or an invalid fabricated node, correct the witness or catalogue and rerun the check; do not weaken the property.

## Change the code on purpose

If an intended claim overlaps another, fold it into the claiming rule or narrow its predicate. `SubNodesRule` is the catch-all and is not part of the overlap check.

Review every changed claim predicate even when the check passes. If a claim depends on a node property or a built-in function not represented by the existing witnesses, add representative positive and negative shapes to `NodeWitnessFactory::shapeWitnesses()`. One instance per class cannot cover every combination of properties.

## Coverage record

Sync commit: `591fcad`, confirmed 2026-11-08 against `nikic/php-parser` v5.9.0 from `composer.lock` and PHP 8.4. The command checked 171 concrete node classes, 181 witnesses, and 13 rules in both modes.

The check calls `children()` on the 13 claim rules listed above. At the sync commit, `ByReferenceCallRule` is the only rule whose decision to claim depends on node properties rather than class. The other listed helpers affect the returned children, not the decision to claim. The check does not cover:

- all node property combinations, custom node subclasses, or functions outside the fixed catalogue
- custom `RuleChain::children()` implementations, because the check flattens chains under the base first-match semantics
- whether a claiming rule returns the correct reads and writes
- `SubNodesRule`, `Detector`, scope binding, or other behaviour outside the claim step

When covered code changes, review the claim predicates and witnesses, then rerun the check. Move the sync commit above only after confirming that the witnesses still represent the changed predicates at the new commit.
