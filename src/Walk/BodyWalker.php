<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use JonBaldie\ExplicitnessChecker\Detect\Detector;
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
    /** @var list<Detector> */
    protected array $detectors;
    public function __construct(
        Bindings $bindings,
        Mode $mode,
        protected AccessRules $rules,
        ByReferenceParameters $parameters,
        bool $constructor = false,
    ) {
        $this->detectors = (new DetectorSet())->select($bindings, $mode, new ReportableFunctionCall($parameters), $constructor);
    }

    /** @return array{FindingCollector, Denotation} */
    public function walk(Node $node, bool $isWrite, FindingCollector $findings, Denotation $denotation): array
    {
        $denotation = $denotation->enter($node);
        foreach ($this->detectors as $detector) {
            $findings = $detector->detect($node, $isWrite, $findings, $denotation);
        }
        foreach ($this->rules->childrenOf($node, $isWrite) as [$child, $childIsWrite]) {
            [$findings, $denotation] = $this->walk($child, $childIsWrite, $findings, $denotation);
        }

        return [$findings, $denotation->leave($node)];
    }
}
