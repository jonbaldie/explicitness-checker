<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Project;

use PhpParser\Node;

final class NodeChildren
{
    /** @return list<Node> */
    public static function of(Node $node): array
    {
        $children = [];
        foreach ($node->getSubNodeNames() as $name) {
            $value = $node->$name;
            $values = is_array($value) ? $value : [$value];
            foreach ($values as $child) {
                if ($child instanceof Node) {
                    $children[] = $child;
                }
            }
        }

        return $children;
    }
}
