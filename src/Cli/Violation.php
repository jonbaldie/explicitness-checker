<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

/**
 * One function-like with implicit inputs or outputs: one row of the report.
 */
class Violation
{
    /**
     * @param list<string> $inputs  implicit input descriptions
     * @param list<string> $outputs implicit output descriptions
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
     * @return list<string>
     */
    public function getInputs(): array
    {
        return $this->inputs;
    }

    /**
     * @return list<string>
     */
    public function getOutputs(): array
    {
        return $this->outputs;
    }

    /**
     * @return Severity::MINOR|Severity::SERIOUS|Severity::CRITICAL
     */
    public function getSeverity(): string
    {
        return Severity::of(array_merge($this->inputs, $this->outputs));
    }
}
