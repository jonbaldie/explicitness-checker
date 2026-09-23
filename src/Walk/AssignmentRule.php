<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * `$a = $b`: the target is written, then the value is read. Destructuring
 * by reference (`[&$x] = $b`) also writes the value.
 */
class AssignmentRule implements ChildAccessRule
{
    public function children(Node $node, bool $isWrite): ?array
    {
        if (!$node instanceof Expr\Assign) {
            return null;
        }

        $children = [[$node->var, true], [$node->expr, false]];
        if (ReferenceDestructuring::bindsReference($node->var)) {
            $children[] = [$node->expr, true];
        }

        return $children;
    }
}
