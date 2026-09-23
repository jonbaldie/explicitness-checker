<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use JonBaldie\ExplicitnessChecker\Detect\GlobalsArrayDetector;
use JonBaldie\ExplicitnessChecker\VariableName;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * `$r = &$x` writes `$r` and hands out a writable reference to `$x`, so `$x`
 * is read and written, as by a by-reference built-in. A simple local bound to
 * `$GLOBALS[...]` is tracked by ReferenceAliases instead, so the binding
 * itself is not mistaken for a read of the global entry.
 */
class ReferenceAssignmentRule implements ChildAccessRule
{
    public function children(Node $node, bool $isWrite): ?array
    {
        if (!$node instanceof Expr\AssignRef) {
            return null;
        }
        if (!GlobalsArrayDetector::isGlobalsFetch($node->expr)) {
            return [[$node->var, true], [$node->expr, false], [$node->expr, true]];
        }
        if (VariableName::of($node->var) === null) {
            return null;
        }

        return [];
    }
}
