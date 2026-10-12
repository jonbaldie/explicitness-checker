<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Scope;

use JonBaldie\ExplicitnessChecker\VariableName;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Stmt\Global_;
use PhpParser\Node\Stmt\Unset_;

/**
 * What each literal name denotes during one function-like walk.
 *
 * Bindings is the whole-body declaration. A reference alias is a live
 * `$GLOBALS[...]` subject. A root is a definite fresh shallow copy of a
 * by-value parameter. A globals alias wins over a root. A `global` or
 * `static` binding, a by-reference parameter, a captured reference, and a
 * live alias never become roots.
 *
 * Callers use `channel` or `mutatedParameter`. `enter` and `leave` are paired
 * once per visited node.
 */
class Denotation
{
    public const GLOBALS_ALIAS = 'globalsAlias';

    protected const FRESH_OBJECT = 'freshObject';

    protected const OWN_STORAGE = 'ownStorage';

    protected const SHARED = 'shared';

    protected ReferenceAliases $aliases;

    protected JoinMap $joinMap;

    public function __construct(protected Bindings $bindings)
    {
        $this->aliases = new ReferenceAliases();
        $this->joinMap = new JoinMap();
    }

    /**
     * The implicit channel of a variable node.
     * A fresh shallow copy of a by-value parameter stays `{kind: parameter, subject: null}`.
     *
     * @return array{kind: Bindings::*|self::GLOBALS_ALIAS|null, subject: string|null}
     */
    public function channel(Node $node): array
    {
        $subject = $this->aliases->targetOf($node);
        if ($subject !== null) {
            return ['kind' => self::GLOBALS_ALIAS, 'subject' => $subject];
        }

        $name = VariableName::of($node);

        return ['kind' => $name === null ? null : $this->bindings->kindOf($name), 'subject' => null];
    }

    /**
     * The parameter whose caller-visible data this write changes, or null when
     * the caller cannot observe it.
     */
    public function mutatedParameter(Node $node, bool $isWrite): ?string
    {
        if (!$isWrite) {
            return null;
        }

        $name = VariableName::of($node);
        if ($name !== null) {
            return $this->writtenVariable($node, $name);
        }
        if (!$node instanceof PropertyFetch) {
            return null;
        }

        return $this->writtenProperty($node);
    }

    public function enter(Node $node): void
    {
        $this->joinMap->enter($node);
        $this->aliases->enter($node);
        $this->dropShadowed($node);
    }

    public function leave(Node $node): void
    {
        $this->aliases->leave($node);
        $this->rebindLeft($node);
        $this->joinMap->leave($node);
    }

    protected function writtenVariable(Node $node, string $name): ?string
    {
        if ($this->aliases->targetOf($node) !== null) {
            return null;
        }
        if ($this->bindings->kindOf($name) !== Bindings::BY_REFERENCE_PARAMETER) {
            return null;
        }

        return $name;
    }

    protected function writtenProperty(PropertyFetch $node): ?string
    {
        $root = $this->rootOf($node);
        $name = VariableName::of($root);
        if ($name === null || $this->aliases->targetOf($root) !== null) {
            return null;
        }

        $origin = $this->joinMap->origin($name);
        if ($origin === null) {
            return $this->bindings->isParameter($name) ? $name : null;
        }

        return $this->reach($node->var, $name) === self::FRESH_OBJECT ? null : $origin;
    }

    protected function rootOf(Node $expr): Node
    {
        while ($expr instanceof PropertyFetch || $expr instanceof ArrayDimFetch) {
            $expr = $expr->var;
        }

        return $expr;
    }

    /**
     * @return self::FRESH_OBJECT|self::OWN_STORAGE|self::SHARED
     */
    protected function reach(Node $expr, string $name): string
    {
        if (VariableName::of($expr) === $name) {
            return self::FRESH_OBJECT;
        }
        if ($expr instanceof ArrayDimFetch) {
            return $this->reach($expr->var, $name) === self::OWN_STORAGE ? self::OWN_STORAGE : self::SHARED;
        }
        if (!$expr instanceof PropertyFetch) {
            return self::SHARED;
        }

        return $this->reach($expr->var, $name) === self::FRESH_OBJECT ? self::OWN_STORAGE : self::SHARED;
    }

    protected function dropShadowed(Node $node): void
    {
        if ($node instanceof AssignRef) {
            $this->joinMap->killBound($node->var);

            return;
        }
        if (!$node instanceof Global_) {
            return;
        }

        foreach ($node->vars as $variable) {
            $this->joinMap->killBound($variable);
        }
    }

    protected function rebindLeft(Node $node): void
    {
        if ($node instanceof Assign) {
            $this->rebindAssign($node);

            return;
        }
        if ($node instanceof AssignOp) {
            $this->joinMap->killBound($node->var);

            return;
        }
        if (!$node instanceof Unset_) {
            return;
        }

        foreach ($node->vars as $variable) {
            $this->joinMap->killBound($variable);
        }
    }

    protected function rebindAssign(Assign $assign): void
    {
        $name = VariableName::of($assign->var);
        if (
            $name !== null
            && ($assign->expr->getType() === 'Expr_Clone' || $assign->expr->getType() === 'Expr_New')
            && $this->bindings->kindOf($name) === Bindings::PARAMETER
            && $this->aliases->targetOf($assign->var) === null
        ) {
            $this->joinMap->establish($name);

            return;
        }

        $this->joinMap->killBound($assign->var);
    }
}
