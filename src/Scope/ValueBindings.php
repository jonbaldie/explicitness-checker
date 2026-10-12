<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Scope;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/** Publishes ordinary-value binding events at their walk evaluation points. */
class ValueBindings
{
    /** @var array<int, array<string, string>> */
    protected array $iterables = [];

    public function __construct(protected ValueOrigins $values, protected JoinMap $map)
    {
    }

    public function enter(Node $node, ?Node $parent): void
    {
        if ($parent instanceof Stmt\Foreach_) {
            $this->bindForeach($node, $parent);
        }
        if ($parent instanceof Stmt\Catch_ && $node === $parent->var) {
            $this->values->forget($node);
        }
        if ($node instanceof Expr\AssignRef) {
            $this->values->bind($node->var, $this->values->of($node->expr));
        }
        if ($node instanceof Stmt\Global_) {
            foreach ($node->vars as $variable) {
                $this->map->killBound($variable);
            }
        }
    }

    public function leave(Node $node): void
    {
        if ($node instanceof Stmt\Foreach_) {
            unset($this->iterables[spl_object_id($node)]);
        }
        if ($node instanceof Expr\Assign) {
            $this->values->assign($node);
        }
        if ($node instanceof Expr\AssignOp) {
            $this->values->forget($node->var);
        }
        if ($node instanceof Stmt\Unset_) {
            foreach ($node->vars as $variable) {
                $this->values->forget($variable);
            }
        }
    }

    protected function bindForeach(Node $node, Stmt\Foreach_ $loop): void
    {
        if ($node !== $loop->keyVar && $node !== $loop->valueVar) {
            return;
        }
        // The iterable was evaluated before either target could overwrite its name.
        $origins = $this->iterables[spl_object_id($loop)] ??= $this->values->of($loop->expr);
        if ($node === $loop->valueVar) {
            $this->values->bindElement($node, $origins);
        }
        if ($node === $loop->keyVar) {
            $this->values->forget($node);
        }
    }
}
