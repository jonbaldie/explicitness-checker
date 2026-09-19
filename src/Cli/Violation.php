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
     * @param list<Finding> $inputs  implicit inputs
     * @param list<Finding> $outputs implicit outputs
     */
    public function __construct(
        protected string $file,
        protected int $line,
        protected string $function,
        protected array $inputs,
        protected array $outputs,
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
     * @return list<string> implicit input descriptions
     */
    public function getInputs(): array
    {
        return self::descriptions($this->inputs);
    }

    /**
     * @return list<string> implicit output descriptions
     */
    public function getOutputs(): array
    {
        return self::descriptions($this->outputs);
    }

    /**
     * @return Severity::MINOR|Severity::SERIOUS|Severity::CRITICAL
     */
    public function getSeverity(): string
    {
        return Severity::of(array_merge($this->inputs, $this->outputs));
    }

    /**
     * @param list<Finding> $findings
     *
     * @return list<string>
     */
    protected static function descriptions(array $findings): array
    {
        return array_map(static fn (Finding $finding): string => $finding->getDescription(), $findings);
    }
}
