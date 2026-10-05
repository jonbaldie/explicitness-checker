<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests\Support;

use JonBaldie\ExplicitnessChecker\Walk\ByReferenceParameters;

/**
 * Built-ins that exist only in a test: each name maps to its by-reference
 * positions and parameter names.
 */
class SyntheticByReferenceParameters implements ByReferenceParameters
{
    /**
     * @param array<string, array{list<int>, list<string>}> $builtins
     */
    public function __construct(protected array $builtins)
    {
    }

    public function isBuiltin(string $function): bool
    {
        return isset($this->builtins[$function]);
    }

    public function isPassedByReference(string $function, int $position, ?string $name): bool
    {
        [$positions, $names] = $this->builtins[$function] ?? [[], []];

        return $name === null ? in_array($position, $positions, true) : in_array($name, $names, true);
    }
}
