<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use PhpParser\Node;

/**
 * `[$k => $v]`: the value is accessed in the item's own mode; the key is always
 * read, as it only looks up the element when destructuring (`[$k => $v] = $xs`).
 */
class ArrayItemRule implements ChildAccessRule
{
    public function children(Node $node, bool $isWrite): ?array
    {
        if (!$node instanceof Node\ArrayItem) {
            return null;
        }

        $children = [];
        if ($node->key !== null) {
            $children[] = [$node->key, false];
        }
        $children[] = [$node->value, $isWrite];

        return $children;
    }
}
