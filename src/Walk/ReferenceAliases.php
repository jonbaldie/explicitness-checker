<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use JonBaldie\ExplicitnessChecker\Detect\GlobalsArrayDetector;
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

    public function enter(Node $node): void
    {
        if ($node instanceof Expr\AssignRef) {
            $this->assignReference($node);

            return;
        }

        if ($node instanceof Stmt\Global_) {
            foreach ($node->vars as $variable) {
                $this->forget($variable);
            }

            return;
        }

        if ($node instanceof Stmt\Foreach_ && $node->byRef) {
            $this->forget($node->valueVar);
        }
    }

    public function leave(Node $node): void
    {
        if (!$node instanceof Stmt\Unset_) {
            return;
        }

        foreach ($node->vars as $variable) {
            if ($variable instanceof Expr\Variable) {
                $this->forget($variable);
            }
        }
    }

    public function targetOf(Node $node): ?string
    {
        $name = VariableName::of($node);

        return $name === null ? null : ($this->aliases[$name] ?? null);
    }

    protected function assignReference(Expr\AssignRef $node): void
    {
        $name = VariableName::of($node->var);
        if ($name === null) {
            return;
        }

        $target = GlobalsArrayDetector::subjectOf($node->expr);
        if ($target === null) {
            $this->forget($node->var);

            return;
        }

        $this->aliases[$name] = $target;
    }

    protected function forget(Node $node): void
    {
        $name = VariableName::of($node);
        if ($name !== null) {
            unset($this->aliases[$name]);
        }
    }
}
