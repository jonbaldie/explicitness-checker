<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * `$o->{$name}` and `Foo::${$name}`: the object or class expression is
 * accessed in the node's own mode; a dynamic property name is always read.
 *
 * Writing `$o->p` first fetches `$o`, so a written object expression is read
 * and then written, as by a by-reference built-in.
 */
class PropertyFetchRule implements ChildAccessRule
{
    public function children(Node $node, bool $isWrite): ?array
    {
        if ($node instanceof Expr\PropertyFetch) {
            $children = $isWrite ? [[$node->var, false], [$node->var, true]] : [[$node->var, false]];
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
