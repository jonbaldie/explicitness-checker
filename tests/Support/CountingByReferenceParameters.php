<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests\Support;

use JonBaldie\ExplicitnessChecker\Walk\ReflectedByReferenceParameters;

/**
 * The reflection-backed lookup, counting how often it reflects on each
 * function name.
 */
class CountingByReferenceParameters extends ReflectedByReferenceParameters
{
    /** @var array<string, int> */
    public array $reflections = [];

    protected function reflect(string $function): ?array
    {
        $this->reflections[$function] = ($this->reflections[$function] ?? 0) + 1;

        return parent::reflect($function);
    }
}
