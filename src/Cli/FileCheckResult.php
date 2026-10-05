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
     * @param list<FunctionResult> $functions every function-like, in source order
     * @param UncheckedInput|null  $unchecked why the file could not be analysed, or null when it was
     */
    public function __construct(
        protected string $file,
        protected array $functions,
        protected ?UncheckedInput $unchecked = null,
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
            $findings = $function->getFindings();
            if ($findings !== []) {
                $violations[] = new Violation($this->file, $function->getLine(), $function->getName(), $findings);
            }
        }

        return $violations;
    }

    /**
     * Why the file could not be read or parsed, or null when it was checked.
     */
    public function getUnchecked(): ?UncheckedInput
    {
        return $this->unchecked;
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
