<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Scope;

use PhpParser\Node;

/** Direct syntax children, in php-parser traversal order. */
class ChildNodes
{
    /** @return list<Node> */
    public static function of(Node $node): array
    {
        $children = [];
        foreach ($node->getSubNodeNames() as $name) {
            $value = $node->$name;
            foreach (is_array($value) ? $value : [$value] as $child) {
                if ($child instanceof Node) {
                    $children[] = $child;
                }
            }
        }

        return $children;
    }
}
