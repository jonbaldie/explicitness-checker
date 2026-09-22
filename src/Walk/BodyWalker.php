<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use JonBaldie\ExplicitnessChecker\Detect\Detector;
use JonBaldie\ExplicitnessChecker\FindingCollector;
use PhpParser\Node;

/**
 * Walks a function-like body depth-first. Every detector sees each visited
 * node before its children; AccessRules decides which children are visited
 * and whether each is read or written.
 */
class BodyWalker
{
    /**
     * @param list<Detector> $detectors
     */
    public function __construct(
        protected array $detectors,
        protected AccessRules $rules,
        protected FindingCollector $findings,
        protected ReferenceAliases $aliases,
    ) {
    }

    public function walk(Node $node, bool $isWrite): void
    {
        $this->aliases->enter($node);

        foreach ($this->detectors as $detector) {
            $detector->detect($node, $isWrite, $this->findings);
        }

        foreach ($this->rules->childrenOf($node, $isWrite) as [$child, $childIsWrite]) {
            $this->walk($child, $childIsWrite);
        }

        $this->aliases->leave($node);
    }
}
