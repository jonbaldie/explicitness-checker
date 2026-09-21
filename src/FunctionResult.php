<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker;

/**
 * What the source-level check found in one function-like.
 */
class FunctionResult
{
    public function __construct(
        protected string $name,
        protected int $line,
        protected FunctionAnalysis $analysis,
    ) {
    }

    /**
     * The name per FunctionLikeNames, e.g. `App\Sub\K::m` or `{closure}`.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * The line where the function-like is declared.
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * Distinct implicit inputs, in order of first occurrence.
     *
     * @return list<Finding>
     */
    public function getInputs(): array
    {
        return $this->analysis->getImplicitInputs();
    }

    /**
     * Distinct implicit outputs, in order of first occurrence.
     *
     * @return list<Finding>
     */
    public function getOutputs(): array
    {
        return $this->analysis->getImplicitOutputs();
    }

    /**
     * The full analysis, for callers who also want the parameters and the
     * names declared with `global`.
     */
    public function getAnalysis(): FunctionAnalysis
    {
        return $this->analysis;
    }
}
