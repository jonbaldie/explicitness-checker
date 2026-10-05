<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

use JonBaldie\ExplicitnessChecker\Finding;

/**
 * One function-like with implicit inputs or outputs: one row of the report.
 */
class Violation
{
    /**
     * @param list<Finding> $findings implicit inputs and outputs, in order of first occurrence
     */
    public function __construct(
        protected string $file,
        protected int $line,
        protected string $function,
        protected array $findings,
    ) {
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getFunction(): string
    {
        return $this->function;
    }

    /**
     * Implicit inputs and outputs, in order of first occurrence.
     *
     * @return list<Finding>
     */
    public function getFindings(): array
    {
        return $this->findings;
    }

    /**
     * @return list<string> implicit input descriptions
     */
    public function getInputs(): array
    {
        return self::descriptions(Finding::inputsOf($this->findings));
    }

    /**
     * @return list<string> implicit output descriptions
     */
    public function getOutputs(): array
    {
        return self::descriptions(Finding::outputsOf($this->findings));
    }

    /**
     * @return Severity::MINOR|Severity::SERIOUS|Severity::CRITICAL
     */
    public function getSeverity(): string
    {
        return Severity::of($this->findings);
    }

    /**
     * @param list<Finding> $findings
     *
     * @return list<string> the findings' descriptions, as the report lists them
     */
    public static function descriptions(array $findings): array
    {
        return array_map(static fn (Finding $finding): string => $finding->getDescription(), $findings);
    }
}
