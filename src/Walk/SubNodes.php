<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use PhpParser\Node;

/**
 * The direct child nodes of a node, in sub-node order.
 */
class SubNodes
{
    /**
     * @return list<Node>
     */
    public static function of(Node $node): array
    {
        $children = [];
        foreach ($node->getSubNodeNames() as $name) {
            $sub = $node->$name;
            $candidates = is_array($sub) ? $sub : [$sub];
            foreach ($candidates as $candidate) {
                if ($candidate instanceof Node) {
                    $children[] = $candidate;
                }
            }
        }

        return $children;
    }
}
