<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use PhpParser\Node;
use PhpParser\Node\Stmt;

/**
 * `foreach ($xs as $k => $v)`: the iterated expression is read, the key and
 * value targets are written, then the body is walked. Iterating by reference
 * (`as &$v`, `as [&$v]`) also writes the iterated expression, as a
 * by-reference built-in does.
 */
class ForeachRule implements ChildAccessRule
{
    public function children(Node $node, bool $isWrite): ?array
    {
        if (!$node instanceof Stmt\Foreach_) {
            return null;
        }

        $children = [[$node->expr, false]];
        if ($node->byRef || ReferenceDestructuring::bindsReference($node->valueVar)) {
            $children[] = [$node->expr, true];
        }
        if ($node->keyVar !== null) {
            $children[] = [$node->keyVar, true];
        }
        $children[] = [$node->valueVar, true];
        foreach ($node->stmts as $stmt) {
            $children[] = [$stmt, false];
        }

        return $children;
    }
}
