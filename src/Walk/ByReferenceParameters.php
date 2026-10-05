<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

/**
 * Which arguments a built-in function takes by reference. Function names are
 * case-insensitive, as in PHP.
 */
interface ByReferenceParameters
{
    /**
     * Whether the function is a built-in whose parameters are known.
     */
    public function isBuiltin(string $function): bool;

    /**
     * Whether the built-in takes the argument at $position, or the argument
     * named $name if it's a named one, by reference. False for functions that
     * aren't built-ins.
     */
    public function isPassedByReference(string $function, int $position, ?string $name): bool;
}
