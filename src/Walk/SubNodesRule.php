<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use PhpParser\Node;

/**
 * Fallback: visit every child, in the same access mode as the node itself.
 */
class SubNodesRule implements ChildAccessRule
{
    public function children(Node $node, bool $isWrite): ?array
    {
        $children = [];
        foreach (SubNodes::of($node) as $child) {
            $children[] = [$child, $isWrite];
        }

        return $children;
    }
}
