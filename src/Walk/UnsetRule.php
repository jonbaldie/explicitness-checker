<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use PhpParser\Node;
use PhpParser\Node\Stmt;

/**
 * `unset($a, $b)`: every argument is written.
 */
class UnsetRule implements ChildAccessRule
{
    public function children(Node $node, bool $isWrite): ?array
    {
        if (!$node instanceof Stmt\Unset_) {
            return null;
        }

        $children = [];
        foreach ($node->vars as $var) {
            $children[] = [$var, true];
        }

        return $children;
    }
}
