<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\FindingCollector;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Default writes, with optional props-mode reads, of `$this->name` and `$this?->name` (the name
 * is shown as "..." when dynamic).
 */
class ObjectPropertyDetector implements Detector
{
    public function __construct(protected bool $reportReads = true)
    {
    }

    public function detect(Node $node, bool $isWrite, FindingCollector $findings): FindingCollector
    {
        if (
            (!$isWrite && !$this->reportReads)
            || (!$node instanceof Expr\PropertyFetch && !$node instanceof Expr\NullsafePropertyFetch)
            || !$node->var instanceof Expr\Variable
            || $node->var->name !== 'this'
        ) {
            return $findings;
        }

        $property = $node->name instanceof Node\Identifier ? $node->name->toString() : '...';
        $subject = 'object property $this->' . $property;
        $findings = $findings->access($isWrite, $subject, Category::OBJECT_PROPERTY, $node);
        return $findings;
    }
}
