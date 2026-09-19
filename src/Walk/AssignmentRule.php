<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * `$a = $b`: the target is written, then the value is read.
 */
class AssignmentRule implements ChildAccessRule
{
    public function children(Node $node, bool $isWrite): ?array
    {
        if (!$node instanceof Expr\Assign) {
            return null;
        }

        return [[$node->var, true], [$node->expr, false]];
    }
}
