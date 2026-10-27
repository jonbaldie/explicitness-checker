<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use JonBaldie\ExplicitnessChecker\ChildNodes;
use JonBaldie\ExplicitnessChecker\LiteralCallable;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * ByReferenceParameters from reflecting on the running PHP, so the answers
 * depend on the extensions loaded. Advancing a snapshot reflects each name
 * once, including unknown names; lookup methods leave the snapshot unchanged.
 */
class ReflectedByReferenceParameters implements ByReferenceParameters
{
    /**
     * @var array<string, list<\ReflectionParameter>|null> by lower-case function
     *                                                     name; null for functions
     *                                                     that aren't built-ins
     */
    protected array $parameters = [];

    public function isBuiltin(string $function): bool
    {
        return $this->parametersOf($function) !== null;
    }

    public function isPassedByReference(string $function, int $position, ?string $name): bool
    {
        return $this->parameter($this->parametersOf($function) ?? [], $position, $name)?->isPassedByReference() === true;
    }

    /**
     * @return list<\ReflectionParameter>|null
     */
    protected function parametersOf(string $function): ?array
    {
        $key = strtolower($function);

        return array_key_exists($key, $this->parameters) ? $this->parameters[$key] : $this->reflect($function);
    }

    /** Cache advancement is explicit; existing snapshots never change. */
    public function withFunction(string $function): self
    {
        $key = strtolower($function);
        if (array_key_exists($key, $this->parameters)) {
            return $this;
        }
        $next = clone $this;
        $next->parameters[$key] = $next->reflect($function);

        return $next;
    }

    /** @param array<Node> $nodes */
    public function forNodes(array $nodes): self
    {
        $next = $this;
        foreach ($nodes as $node) {
            if ($node instanceof Expr\FuncCall) {
                $function = (new LiteralCallable($node))->referenceForwardingFunction();
                if ($function !== null) {
                    $next = $next->withFunction($function);
                }
                if ($node->name instanceof Node\Name && $node->args === []) {
                    $next = $next->withFunction($node->name->toString());
                }
            }
            $next = $next->forNodes(ChildNodes::of($node));
        }

        return $next;
    }

    /**
     * @return list<\ReflectionParameter>|null
     */
    protected function reflect(string $function): ?array
    {
        if (!function_exists($function)) {
            return null;
        }
        $reflection = new \ReflectionFunction($function);

        return $reflection->isInternal() ? $reflection->getParameters() : null;
    }

    /**
     * @param list<\ReflectionParameter> $parameters
     */
    protected function parameter(array $parameters, int $position, ?string $name): ?\ReflectionParameter
    {
        if ($name === null) {
            $last = end($parameters);

            return $parameters[$position] ?? ($last !== false && $last->isVariadic() ? $last : null);
        }
        foreach ($parameters as $parameter) {
            if ($parameter->getName() === $name) {
                return $parameter;
            }
        }

        return null;
    }
}
