<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker;

/**
 * What the Analyser found in one function-like.
 */
class FunctionAnalysis
{
    /**
     * @param list<Finding> $findings distinct implicit inputs and outputs, in order of first occurrence
     * @param list<string> $parameters
     * @param list<string> $declaredGlobals
     */
    public function __construct(
        protected array $findings,
        protected array $parameters,
        protected array $declaredGlobals,
    ) {
    }

    /**
     * Distinct implicit inputs and outputs, in order of first occurrence.
     *
     * @return list<Finding>
     */
    public function getFindings(): array
    {
        return $this->findings;
    }

    /**
     * Distinct implicit inputs, in order of first occurrence.
     *
     * @return list<Finding>
     */
    public function getImplicitInputs(): array
    {
        return Finding::inputsOf($this->findings);
    }

    /**
     * Distinct implicit outputs, in order of first occurrence.
     *
     * @return list<Finding>
     */
    public function getImplicitOutputs(): array
    {
        return Finding::outputsOf($this->findings);
    }

    /**
     * Names of the function-like's parameters, without "$".
     *
     * @return list<string>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Names declared with `global` in the body, without "$", in discovery order.
     *
     * @return list<string>
     */
    public function getDeclaredGlobals(): array
    {
        return $this->declaredGlobals;
    }
}
