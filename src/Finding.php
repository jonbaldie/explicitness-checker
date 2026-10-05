<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker;

/**
 * One distinct implicit input or output of a function-like.
 */
class Finding
{
    public function __construct(
        protected string $description,
        protected string $category,
        protected int $line,
        protected ?string $variable = null,
        protected bool $output = false,
    ) {
    }

    /**
     * The CLI's wording, e.g. "read from global variable $config".
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * One of the Category constants.
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * The line where this input or output first occurs in the function-like.
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * The name of the variable read or written, without the `$`, e.g. "_ENV":
     * a global, superglobal, static variable, captured reference or mutated
     * argument. Null for anything else, including `$GLOBALS` entries and
     * properties.
     */
    public function getVariable(): ?string
    {
        return $this->variable;
    }

    /**
     * Whether this is an implicit input: something the function-like reads.
     */
    public function isInput(): bool
    {
        return !$this->output;
    }

    /**
     * Whether this is an implicit output: something the function-like writes.
     */
    public function isOutput(): bool
    {
        return $this->output;
    }

    /**
     * @param list<Finding> $findings
     *
     * @return list<Finding> the inputs among $findings, in their order
     */
    public static function inputsOf(array $findings): array
    {
        return array_values(array_filter($findings, static fn (Finding $finding): bool => $finding->isInput()));
    }

    /**
     * @param list<Finding> $findings
     *
     * @return list<Finding> the outputs among $findings, in their order
     */
    public static function outputsOf(array $findings): array
    {
        return array_values(array_filter($findings, static fn (Finding $finding): bool => $finding->isOutput()));
    }
}
