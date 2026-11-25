<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use JonBaldie\ExplicitnessChecker\ChildNodes;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Fallback for nodes no other rule claims: visit every child, in the same
 * access mode as the node itself. It claims every node, so AccessRules asks it
 * only after all other rules have declined.
 *
 * A call is the exception: its children are evaluated expressions and are
 * always read, even when the call result is a write target
 * (`make($r)->p = 1` writes the returned object's property, not `$r`).
 * Constructor calls (`new C($r)`) are calls in the same sense. By-reference
 * built-in arguments are claimed by ByReferenceCallRule instead.
 */
class SubNodesRule implements ChildAccessRule
{
    /**
     * @return list<array{Node, bool}>
     */
    public function children(Node $node, bool $isWrite): array
    {
        $children = [];
        foreach (ChildNodes::of($node) as $child) {
            $children[] = [$child, $isWrite && !$this->isCall($node)];
        }

        return $children;
    }

    protected function isCall(Node $node): bool
    {
        return $node instanceof Expr\FuncCall
            || $node instanceof Expr\MethodCall
            || $node instanceof Expr\NullsafeMethodCall
            || $node instanceof Expr\StaticCall
            || $node instanceof Expr\New_;
    }
}
