<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

/**
 * What checking one PHP file produced, including whether it was analysable.
 */
class FileCheckResult
{
    /**
     * @param list<Violation> $violations
     */
    public function __construct(
        protected array $violations,
        protected bool $parseError,
    ) {
    }

    /**
     * @return list<Violation>
     */
    public function getViolations(): array
    {
        return $this->violations;
    }

    public function hasParseError(): bool
    {
        return $this->parseError;
    }
}
