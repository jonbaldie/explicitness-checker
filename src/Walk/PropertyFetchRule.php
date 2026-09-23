<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * `$o->{$name}` and `Foo::${$name}`: the object or class expression is
 * accessed in the node's own mode; a dynamic property name is always read.
 */
class PropertyFetchRule implements ChildAccessRule
{
    public function children(Node $node, bool $isWrite): ?array
    {
        if ($node instanceof Expr\PropertyFetch) {
            $children = [[$node->var, $isWrite]];
        } elseif ($node instanceof Expr\StaticPropertyFetch) {
            $children = $node->class instanceof Expr ? [[$node->class, $isWrite]] : [];
        } else {
            return null;
        }

        if ($node->name instanceof Expr) {
            $children[] = [$node->name, false];
        }

        return $children;
    }
}
