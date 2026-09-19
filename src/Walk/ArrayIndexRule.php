<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * `$a[$k]`: the array is accessed in the node's own mode; the index is always read.
 *
 * `$GLOBALS[...]` never reaches this rule: LeafRule claims it first.
 */
class ArrayIndexRule implements ChildAccessRule
{
    public function children(Node $node, bool $isWrite): ?array
    {
        if (!$node instanceof Expr\ArrayDimFetch) {
            return null;
        }

        $children = [[$node->var, $isWrite]];
        if ($node->dim !== null) {
            $children[] = [$node->dim, false];
        }

        return $children;
    }
}
