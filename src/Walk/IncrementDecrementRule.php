<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * `$a++`, `--$a` and friends: the target is read, then written.
 */
class IncrementDecrementRule implements ChildAccessRule
{
    public function children(Node $node, bool $isWrite): ?array
    {
        if (
            $node instanceof Expr\PreInc
            || $node instanceof Expr\PostInc
            || $node instanceof Expr\PreDec
            || $node instanceof Expr\PostDec
        ) {
            return [[$node->var, false], [$node->var, true]];
        }

        return null;
    }
}
