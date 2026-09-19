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
}
