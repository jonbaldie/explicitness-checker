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
     * The name of the variable read or written, without the `$`, e.g. "_ENV";
     * null when the finding is not about a named variable.
     */
    public function getVariable(): ?string
    {
        return $this->variable;
    }
}
