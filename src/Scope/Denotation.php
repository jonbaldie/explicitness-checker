<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Scope;

use JonBaldie\ExplicitnessChecker\VariableName;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * What each literal name denotes during one function-like walk.
 * Declaration and live GLOBALS-reference channels take precedence over ordinary
 * value provenance. Enter/leave publish binding facts only after evaluation.
 */
class Denotation
{
    public const GLOBALS_ALIAS = 'globalsAlias';

    protected ReferenceAliases $aliases;
    protected JoinMap $joinMap;
    protected ValueOrigins $values;
    protected ValueBindings $events;

    public function __construct(protected Bindings $bindings)
    {
        $this->aliases = new ReferenceAliases();
        $this->joinMap = new JoinMap(ValueOrigins::initial($bindings));
        $this->values = new ValueOrigins($bindings, $this->aliases, $this->joinMap);
        $this->events = new ValueBindings();
    }

    /** @return array{kind: Bindings::*|self::GLOBALS_ALIAS|null, subject: string|null} */
    public function channel(Node $node): array
    {
        $subject = $this->aliases->targetOf($node);
        if ($subject !== null) {
            return ['kind' => self::GLOBALS_ALIAS, 'subject' => $subject];
        }
        $name = VariableName::of($node);

        return ['kind' => $name === null ? null : $this->bindings->kindOf($name), 'subject' => null];
    }

    /** @return list<string> */
    public function mutatedParameters(Node $node, bool $isWrite): array
    {
        if (!$isWrite) {
            return [];
        }
        $name = VariableName::of($node);
        if ($name !== null) {
            return $this->aliases->targetOf($node) === null
                && $this->bindings->kindOf($name) === Bindings::BY_REFERENCE_PARAMETER ? [$name] : [];
        }
        if (!$node instanceof Expr\PropertyFetch) {
            return [];
        }

        return array_keys(array_filter(
            $this->values->of($node->var),
            static fn (string $reach): bool => $reach !== ValueOrigins::FRESH_OBJECT,
        ));
    }

    public function enter(Node $node): self
    {
        $next = clone $this;
        $parent = $this->joinMap->parent();
        $aliases = $this->aliases->enter($node);
        $map = $this->joinMap->enter($node);
        $values = $this->values->withContext($aliases, $map);
        [$events, $values] = $this->events->enter($node, $parent, $values);
        $next->aliases = $aliases;
        $next->joinMap = $values->getMap();
        $next->values = $values;
        $next->events = $events;

        return $next;
    }

    public function leave(Node $node): self
    {
        $next = clone $this;
        $aliases = $this->aliases->leave($node);
        $values = $this->values->withContext($aliases, $this->joinMap);
        [$events, $values] = $this->events->leave($node, $values);
        $map = $values->getMap()->leave($node);
        $next->aliases = $aliases;
        $next->joinMap = $map;
        $next->values = $values->withContext($aliases, $map);
        $next->events = $events;

        return $next;
    }
}
