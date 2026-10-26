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
    protected JoinMap $joinMap;
    protected ValueOrigins $values;
    protected ValueBindings $events;

    public function __construct(protected Bindings $bindings)
    {
        $this->joinMap = new JoinMap(ValueOrigins::initial($bindings));
        $this->values = new ValueOrigins($bindings, $this->joinMap->aliases(), $this->joinMap);
        $this->events = new ValueBindings();
    }

    /** @return array{kind: Bindings::*|null, subjects: list<string>, ordinary: bool} */
    public function channel(Node $node): array
    {
        $aliases = $this->joinMap->aliases();
        $name = VariableName::of($node);

        return [
            'kind' => $name === null ? null : $this->bindings->kindOf($name),
            'subjects' => array_values(array_filter($aliases->alternatives($node), static fn (?string $subject): bool => $subject !== null)),
            'ordinary' => $aliases->mayBeOrdinary($node),
        ];
    }

    /** @return list<string> */
    public function mutatedParameters(Node $node, bool $isWrite): array
    {
        if (!$isWrite) {
            return [];
        }
        $name = VariableName::of($node);
        if ($name !== null) {
            return $this->joinMap->aliases()->mayBeOrdinary($node)
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
        $map = $this->joinMap->enter($node);
        $values = $this->values->withContext($map->aliases(), $map);
        [$events, $values] = $this->events->enter($node, $parent, $values);
        $next->joinMap = $values->getMap();
        $next->values = $values;
        $next->events = $events;

        return $next;
    }

    public function leave(Node $node): self
    {
        $next = clone $this;
        $map = $this->joinMap->leaveBindings($node);
        $values = $this->values->withContext($map->aliases(), $map);
        [$events, $values] = $this->events->leave($node, $values);
        $map = $values->getMap()->leave($node);
        $next->joinMap = $map;
        $next->values = $values->withContext($map->aliases(), $map);
        $next->events = $events;

        return $next;
    }
}
