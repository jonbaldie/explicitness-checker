<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Scope;

use JonBaldie\ExplicitnessChecker\VariableName;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Bounded syntactic provenance for ordinary object handles and copied arrays.
 * No return-value, magic getter, custom clone or heap alias inference.
 * Facts are values, not references to another variable's changing facts.
 */
class ValueOrigins
{
    public const FRESH_OBJECT = 'freshObject';
    public const OWN_STORAGE = 'ownStorage';
    public const SHARED = 'shared';

    protected ValueSelections $selections;

    public function __construct(
        protected Bindings $bindings,
        protected ReferenceAliases $aliases,
        protected JoinMap $map,
    ) {
        $this->selections = new ValueSelections();
    }

    public function withContext(ReferenceAliases $aliases, JoinMap $map): self
    {
        $next = clone $this;
        $next->aliases = $aliases;
        $next->map = $map;

        return $next;
    }

    public function getMap(): JoinMap
    {
        return $this->map;
    }

    /** @return array<string, array<string, string>> */
    public static function initial(Bindings $bindings): array
    {
        $roots = [];
        foreach ($bindings->getParameters() as $name) {
            if ($bindings->isParameter($name)) {
                $roots[$name] = [$name => self::SHARED];
            }
        }

        return $roots;
    }

    /** @return array<string, string> */
    public function of(Node $node): array
    {
        $name = VariableName::of($node);
        if ($name !== null) {
            return $this->aliases->targetOf($node) === null ? $this->map->origins($name) : [];
        }
        return $this->selections->of($node, $this);
    }

    /** @param array<string, string> $origins */
    public function bind(Node $target, array $origins): self
    {
        $next = clone $this;
        if ($target instanceof Expr\List_) {
            foreach ($target->items as $item) {
                if ($item !== null) {
                    $next = $next->bind($item->value, ValueSelections::selected($origins, true));
                }
            }

            return $next;
        }
        $name = VariableName::of($target);
        if ($name === null || !$next->canBind($target, $name)) {
            return $next;
        }
        // Keep the existing bounded parameter policy for unknown sources (#159).
        if ($origins === [] && $next->bindings->isParameter($name)) {
            $origins = [$name => self::SHARED];
        }
        $next->map = $next->map->bind($name, $origins);
        return $next;
    }

    public function assign(Expr\Assign $node): self
    {
        $next = clone $this;
        $origins = $next->of($node->expr);
        $name = VariableName::of($node->var);
        if ($node->expr instanceof Expr\New_ && $name !== null && $next->bindings->isParameter($name)) {
            $origins = [$name => self::FRESH_OBJECT];
        }
        $next = $next->bind($node->var, $origins);
        return $next;
    }

    public function forget(Node $target): self
    {
        $next = clone $this;
        $next->map = $next->map->killBound($target);
        $next = $next->bind($target, []);
        return $next;
    }

    /** @param array<string, string> $origins */
    public function bindElement(Node $target, array $origins): self
    {
        $next = clone $this;
        $next = $next->bind($target, ValueSelections::selected($origins, true));
        return $next;
    }

    protected function canBind(Node $target, string $name): bool
    {
        $kind = $this->bindings->kindOf($name);

        return ($kind === null || $kind === Bindings::PARAMETER)
            && $this->aliases->targetOf($target) === null;
    }

}
