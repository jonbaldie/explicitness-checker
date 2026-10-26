<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Scope;

use JonBaldie\ExplicitnessChecker\GlobalsArray;
use JonBaldie\ExplicitnessChecker\VariableName;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Possible GLOBALS-reference bindings within one function-like walk.
 * JoinMap owns all fork snapshots and merges; this value owns only binding
 * events and alternative union. Missing names mean no GLOBALS reference.
 * Unset forgets the local binding without reporting the unset as a write.
 */
class ReferenceAliases
{
    /** @var array<string, list<string|null>> Possible subjects; null means no GLOBALS reference. */
    protected array $aliases = [];

    public function enter(Node $node, ?Node $parent): self
    {
        $next = clone $this;
        if ($node instanceof Expr\AssignRef) {
            $next = $next->assignReference($node);

            return $next;
        }

        if ($node instanceof Stmt\Global_) {
            foreach ($node->vars as $variable) {
                $next = $next->forget($variable);
            }

            return $next;
        }

        if ($parent instanceof Stmt\Foreach_ && $parent->byRef && $node === $parent->valueVar) {
            $next = $next->forget($node);
        }
        return $next;
    }

    public function leave(Node $node): self
    {
        $next = clone $this;
        if (!$node instanceof Stmt\Unset_) {
            return $next;
        }

        foreach ($node->vars as $variable) {
            if ($variable instanceof Expr\Variable) {
                $next = $next->forget($variable);
            }
        }
        return $next;
    }

    /** @return list<string|null> */
    public function alternatives(Node $node): array
    {
        $name = VariableName::of($node);

        return $name === null ? [null] : ($this->aliases[$name] ?? [null]);
    }

    public function mayBeOrdinary(Node $node): bool
    {
        return in_array(null, $this->alternatives($node), true);
    }

    public function merge(self $other): self
    {
        $next = clone $this;
        foreach (array_unique(array_merge(array_keys($this->aliases), array_keys($other->aliases))) as $name) {
            $next->aliases[$name] = array_values(array_unique(array_merge(
                $this->aliases[$name] ?? [null],
                $other->aliases[$name] ?? [null],
            ), SORT_REGULAR));
        }

        return $next;
    }

    protected function assignReference(Expr\AssignRef $node): self
    {
        $next = clone $this;
        $name = VariableName::of($node->var);
        if ($name === null) {
            return $next;
        }

        $target = GlobalsArray::subjectOf($node->expr);
        if ($target === null) {
            $next = $next->forget($node->var);

            return $next;
        }

        $next->aliases[$name] = [$target];
        return $next;
    }

    protected function forget(Node $node): self
    {
        $next = clone $this;
        $name = VariableName::of($node);
        if ($name !== null) {
            $next->aliases[$name] = [null];
        }
        return $next;
    }
}
