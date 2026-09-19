<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use PhpParser\Node;
use PhpParser\Node\Stmt;

/**
 * `catch (E $e)`: the exception variable is written, then the body is walked.
 */
class CatchRule implements ChildAccessRule
{
    public function children(Node $node, bool $isWrite): ?array
    {
        if (!$node instanceof Stmt\Catch_) {
            return null;
        }

        $children = [];
        if ($node->var !== null) {
            $children[] = [$node->var, true];
        }
        foreach ($node->stmts as $stmt) {
            $children[] = [$stmt, false];
        }

        return $children;
    }
}
