<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use JonBaldie\ExplicitnessChecker\Detect\DetectorSet;
use JonBaldie\ExplicitnessChecker\Detect\ReportableFunctionCall;
use JonBaldie\ExplicitnessChecker\FindingCollector;
use JonBaldie\ExplicitnessChecker\Mode;
use JonBaldie\ExplicitnessChecker\Scope\Bindings;
use JonBaldie\ExplicitnessChecker\Scope\Denotation;
use PhpParser\Node;

/** Threads immutable findings and binding snapshots through the access walk. */
class BodyWalker
{
    protected ReportableFunctionCall $reportable;
    public function __construct(
        protected Bindings $bindings,
        protected Mode $mode,
        protected AccessRules $rules,
        protected ByReferenceParameters $parameters,
        protected bool $constructor = false,
    ) {
        $this->reportable = new ReportableFunctionCall($parameters);
    }

    /** @return array{FindingCollector, Denotation} */
    public function walk(Node $node, bool $isWrite, FindingCollector $findings, Denotation $denotation): array
    {
        $denotation = $denotation->enter($node);
        foreach ((new DetectorSet())->select($this->bindings, $this->mode, $denotation, $this->reportable, $this->constructor) as $detector) {
            $findings = $detector->detect($node, $isWrite, $findings);
        }
        foreach ($this->rules->childrenOf($node, $isWrite) as [$child, $childIsWrite]) {
            [$findings, $denotation] = $this->walk($child, $childIsWrite, $findings, $denotation);
        }

        return [$findings, $denotation->leave($node)];
    }
}
