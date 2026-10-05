<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

/**
 * ByReferenceParameters from reflecting on the running PHP, so the answers
 * depend on the extensions loaded. Each function is reflected once, the
 * first time it's asked about.
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

    public function isByReference(string $function, int $position, ?string $name): bool
    {
        return $this->parameter($this->parametersOf($function) ?? [], $position, $name)?->isPassedByReference() === true;
    }

    /**
     * @return list<\ReflectionParameter>|null
     */
    protected function parametersOf(string $function): ?array
    {
        $key = strtolower($function);
        if (!array_key_exists($key, $this->parameters)) {
            $this->parameters[$key] = $this->reflect($function);
        }

        return $this->parameters[$key];
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
