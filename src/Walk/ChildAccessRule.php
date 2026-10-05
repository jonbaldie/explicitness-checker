<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use PhpParser\Node;

/**
 * Decides which children of a node the walk visits, and whether each child is
 * visited as a read or as a write.
 *
 * AccessRules asks its rules in order and the first one that returns a list
 * wins; no two of its rules return a list for the same node.
 */
interface ChildAccessRule
{
    /**
     * @param bool $isWrite whether the node itself is being written to
     *
     * @return list<array{Node, bool}>|null pairs of [child, child is written to] in
     *                                      visit order, or null if the rule doesn't
     *                                      apply to this node
     */
    public function children(Node $node, bool $isWrite): ?array;
}
