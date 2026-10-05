<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use PhpParser\Node;

/**
 * Fallback for nodes no other rule claims: visit every child, in the same
 * access mode as the node itself. It claims every node, so AccessRules asks it
 * only after all other rules have declined.
 */
class SubNodesRule implements ChildAccessRule
{
    /**
     * @return list<array{Node, bool}>
     */
    public function children(Node $node, bool $isWrite): array
    {
        $children = [];
        foreach (SubNodes::of($node) as $child) {
            $children[] = [$child, $isWrite];
        }

        return $children;
    }
}
