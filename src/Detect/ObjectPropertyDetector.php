<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\FindingCollector;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Props mode: reads and writes of `$this->name` and `$this?->name` (the name
 * is shown as "..." when dynamic).
 */
class ObjectPropertyDetector implements Detector
{
    public function detect(Node $node, bool $isWrite, FindingCollector $findings): void
    {
        if (
            (!$node instanceof Expr\PropertyFetch && !$node instanceof Expr\NullsafePropertyFetch)
            || !$node->var instanceof Expr\Variable
            || $node->var->name !== 'this'
        ) {
            return;
        }

        $property = $node->name instanceof Node\Identifier ? $node->name->toString() : '...';
        $subject = 'object property $this->' . $property;
        $findings->access($isWrite, $subject, Category::OBJECT_PROPERTY, $node);
    }
}
