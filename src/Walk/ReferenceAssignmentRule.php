<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use JonBaldie\ExplicitnessChecker\Detect\GlobalsArrayDetector;
use JonBaldie\ExplicitnessChecker\VariableName;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * A simple local bound to `$GLOBALS[...]` is tracked by ReferenceAliases, so
 * the binding itself is not mistaken for a read of the global entry.
 */
class ReferenceAssignmentRule implements ChildAccessRule
{
    public function children(Node $node, bool $isWrite): ?array
    {
        if (
            !$node instanceof Expr\AssignRef
            || VariableName::of($node->var) === null
            || !GlobalsArrayDetector::isGlobalsFetch($node->expr)
        ) {
            return null;
        }

        return [];
    }
}
