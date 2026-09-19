<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker;

/**
 * What the Analyser found in one function-like.
 */
class FunctionAnalysis
{
    /**
     * @param list<Finding> $implicitInputs
     * @param list<Finding> $implicitOutputs
     * @param list<string> $parameters
     * @param list<string> $declaredGlobals
     */
    public function __construct(
        protected array $implicitInputs,
        protected array $implicitOutputs,
        protected array $parameters,
        protected array $declaredGlobals,
    ) {
    }

    /**
     * Distinct implicit inputs, in order of first occurrence.
     *
     * @return list<Finding>
     */
    public function getImplicitInputs(): array
    {
        return $this->implicitInputs;
    }

    /**
     * Distinct implicit outputs, in order of first occurrence.
     *
     * @return list<Finding>
     */
    public function getImplicitOutputs(): array
    {
        return $this->implicitOutputs;
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
