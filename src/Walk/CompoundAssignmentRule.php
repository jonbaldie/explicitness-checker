<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * `$a += $b`: the target is read, then written, then the value is read.
 */
class CompoundAssignmentRule implements ChildAccessRule
{
    public function children(Node $node, bool $isWrite): ?array
    {
        if (!$node instanceof Expr\AssignOp) {
            return null;
        }

        return [[$node->var, false], [$node->var, true], [$node->expr, false]];
    }
}
