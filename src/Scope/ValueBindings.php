<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Scope;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/** Returns binding events and their updated value state at evaluation points. */
class ValueBindings
{
    /** @var array<int, array<string, string>> */
    protected array $iterables = [];

    /** @return array{self, ValueOrigins} */
    public function enter(Node $node, ?Node $parent, ValueOrigins $values): array
    {
        $next = clone $this;
        if ($parent instanceof Stmt\Foreach_) {
            [$next, $values] = $next->bindForeach($node, $parent, $values);
        }
        if ($parent instanceof Stmt\Catch_ && $node === $parent->var) {
            $values = $values->forget($node);
        }
        if ($node instanceof Expr\AssignRef) {
            $values = $values->bind($node->var, $values->of($node->expr));
        }
        if ($node instanceof Stmt\Global_) {
            foreach ($node->vars as $variable) {
                $values = $values->forget($variable);
            }
        }

        return [$next, $values];
    }

    /** @return array{self, ValueOrigins} */
    public function leave(Node $node, ValueOrigins $values): array
    {
        $next = clone $this;
        if ($node instanceof Stmt\Foreach_) {
            unset($next->iterables[spl_object_id($node)]);
        }
        if ($node instanceof Expr\Assign) {
            $values = $values->assign($node);
        }
        if ($node instanceof Expr\AssignOp) {
            $values = $values->forget($node->var);
        }
        if ($node instanceof Stmt\Unset_) {
            foreach ($node->vars as $variable) {
                $values = $values->forget($variable);
            }
        }

        return [$next, $values];
    }

    /** @return array{self, ValueOrigins} */
    protected function bindForeach(Node $node, Stmt\Foreach_ $loop, ValueOrigins $values): array
    {
        $next = clone $this;
        if ($node !== $loop->keyVar && $node !== $loop->valueVar) {
            return [$next, $values];
        }
        $origins = $next->iterables[spl_object_id($loop)] ??= $values->of($loop->expr);
        if ($node === $loop->valueVar) {
            $values = $values->bindElement($node, $origins);
        }
        if ($node === $loop->keyVar) {
            $values = $values->forget($node);
        }

        return [$next, $values];
    }
}
