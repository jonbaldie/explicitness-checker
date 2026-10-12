<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Scope;

use JonBaldie\ExplicitnessChecker\GlobalsArray;
use JonBaldie\ExplicitnessChecker\VariableName;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Tracks local variables that currently reference a `$GLOBALS[...]` entry.
 *
 * The state belongs to one function-like walk. It is deliberately syntactic:
 * a by-reference assignment records a known target, another reference
 * assignment replaces it, and unsetting the alias removes it after the unset
 * has been visited as a write.
 */
class ReferenceAliases
{
    /** @var array<string, string> alias name => globals-array subject */
    protected array $aliases = [];

    public function enter(Node $node): self
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

        if ($node instanceof Stmt\Foreach_ && $node->byRef) {
            $next = $next->forget($node->valueVar);
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

    public function targetOf(Node $node): ?string
    {
        $name = VariableName::of($node);

        return $name === null ? null : ($this->aliases[$name] ?? null);
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

        $next->aliases[$name] = $target;
        return $next;
    }

    protected function forget(Node $node): self
    {
        $next = clone $this;
        $name = VariableName::of($node);
        if ($name !== null) {
            unset($next->aliases[$name]);
        }
        return $next;
    }
}
