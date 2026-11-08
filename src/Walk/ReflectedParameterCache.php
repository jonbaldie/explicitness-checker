<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

class ReflectedParameterCache
{
    /** @var array<string, self> */
    protected array $children = [];

    /** @var array<string, list<\ReflectionParameter>|null> */
    protected array $entries = [];

    /** @return array{list<\ReflectionParameter>|null}|null */
    public function find(string $key): ?array
    {
        return $this->findAt($key, hash('sha256', $key), 0);
    }

    /** @param list<\ReflectionParameter>|null $parameters */
    public function with(string $key, ?array $parameters): self
    {
        return $this->withAt($key, $parameters, hash('sha256', $key), 0);
    }

    /** @return array{list<\ReflectionParameter>|null}|null */
    protected function findAt(string $key, string $hash, int $depth): ?array
    {
        if ($this->children === []) {
            return array_key_exists($key, $this->entries) ? [$this->entries[$key]] : null;
        }
        $child = $this->children[$hash[$depth]] ?? null;

        return $child?->findAt($key, $hash, $depth + 1);
    }

    /** @param list<\ReflectionParameter>|null $parameters */
    protected function withAt(string $key, ?array $parameters, string $hash, int $depth): self
    {
        $next = clone $this;
        if ($this->children === [] && ($this->entries === [] || $depth === strlen($hash))) {
            $next->entries[$key] = $parameters;

            return $next;
        }
        if ($this->children === []) {
            foreach ($this->entries as $existingKey => $existingParameters) {
                $existingKey = (string) $existingKey;
                $existingHash = hash('sha256', $existingKey);
                $next->children[$existingHash[$depth]] = (new self())->withAt(
                    $existingKey,
                    $existingParameters,
                    $existingHash,
                    $depth + 1,
                );
            }
            $next->entries = [];
        }
        $index = $hash[$depth];
        $child = $next->children[$index] ?? new self();
        $next->children[$index] = $child->withAt($key, $parameters, $hash, $depth + 1);

        return $next;
    }
}
