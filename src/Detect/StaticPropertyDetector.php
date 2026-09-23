<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\FindingCollector;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Default mode: reads and writes of `ClassName::$name` (the class is shown as
 * "..." when it is an expression, the property name as "$..." when dynamic).
 */
class StaticPropertyDetector implements Detector
{
    public function detect(Node $node, bool $isWrite, FindingCollector $findings): void
    {
        if (!$node instanceof Expr\StaticPropertyFetch) {
            return;
        }

        $class = $node->class instanceof Node\Name ? $node->class->toString() : '...';
        $property = $node->name instanceof Node\VarLikeIdentifier
            ? '$' . $node->name->toString()
            : '$...';
        $subject = 'static property ' . $class . '::' . $property;
        $findings->access($isWrite, $subject, Category::STATIC_PROPERTY, $node);
    }
}
