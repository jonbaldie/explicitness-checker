<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Scope;

/**
 * A by-value parameter that currently denotes a fresh shallow copy of itself.
 */
class FreshShallow
{
    public function __construct(protected string $origin)
    {
    }

    public function getOrigin(): string
    {
        return $this->origin;
    }
}
