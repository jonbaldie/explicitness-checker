<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use JonBaldie\ExplicitnessChecker\GlobalsArray;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * `$a[$k]`: the array is accessed in the node's own mode; the index is always read.
 *
 * `$GLOBALS[...]` is reported as a whole, so neither it nor its dimension is walked.
 */
class ArrayIndexRule implements ChildAccessRule
{
    public function children(Node $node, bool $isWrite): ?array
    {
        if (!$node instanceof Expr\ArrayDimFetch) {
            return null;
        }
        if (GlobalsArray::isFetch($node)) {
            return [];
        }

        $children = [[$node->var, $isWrite]];
        if ($node->dim !== null) {
            $children[] = [$node->dim, false];
        }

        return $children;
    }
}
