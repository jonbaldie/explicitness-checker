<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\FindingCollector;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Props mode: reads and writes of `$this->name`.
 */
class ObjectPropertyDetector implements Detector
{
    public function detect(Node $node, bool $isWrite, FindingCollector $findings): void
    {
        if (
            !$node instanceof Expr\PropertyFetch
            || !$node->var instanceof Expr\Variable
            || $node->var->name !== 'this'
            || !$node->name instanceof Node\Identifier
        ) {
            return;
        }

        $subject = 'object property $this->' . $node->name->toString();
        $findings->access($isWrite, $subject, Category::OBJECT_PROPERTY, $node);
    }
}
