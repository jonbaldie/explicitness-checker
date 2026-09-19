<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use PhpParser\Node;

/**
 * Decides which children of a node the walk visits, and whether each child is
 * visited as a read or as a write.
 *
 * Rules are tried in order by AccessRules; the first one that returns a list wins.
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
