<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

use JonBaldie\ExplicitnessChecker\FunctionResult;

/**
 * What checking one PHP file produced: every function-like it contains, or
 * why it could not be analysed.
 */
class FileCheckResult
{
    /**
     * @param list<FunctionResult> $functions  every function-like, in source order
     * @param bool                 $unreadable whether the file could not be read
     * @param string|null          $parseError the parser's message when the file did not parse
     */
    public function __construct(
        protected string $file,
        protected array $functions,
        protected bool $unreadable = false,
        protected ?string $parseError = null,
    ) {
    }

    /**
     * The file's path, as it was given to the checker.
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * Every function-like in the file, with or without findings; empty when
     * the file could not be read or parsed.
     *
     * @return list<FunctionResult>
     */
    public function getFunctions(): array
    {
        return $this->functions;
    }

    /**
     * The function-likes with implicit inputs or outputs, as report rows.
     *
     * @return list<Violation>
     */
    public function getViolations(): array
    {
        $violations = [];
        foreach ($this->functions as $function) {
            $inputs = $function->getInputs();
            $outputs = $function->getOutputs();
            if ($inputs !== [] || $outputs !== []) {
                $violations[] = new Violation($this->file, $function->getLine(), $function->getName(), $inputs, $outputs);
            }
        }

        return $violations;
    }

    public function isUnreadable(): bool
    {
        return $this->unreadable;
    }

    public function hasParseError(): bool
    {
        return $this->parseError !== null;
    }

    /**
     * The parser's message, or null when the file parsed or was not read.
     */
    public function getParseError(): ?string
    {
        return $this->parseError;
    }

    /**
     * How many function-likes the file contained; 0 when it could not be
     * read or parsed.
     */
    public function getChecked(): int
    {
        return count($this->functions);
    }
}
