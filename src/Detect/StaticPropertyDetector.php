<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\FindingCollector;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Props mode: reads and writes of `ClassName::$name` (the class is shown as
 * "..." when it is an expression).
 */
class StaticPropertyDetector implements Detector
{
    public function detect(Node $node, bool $isWrite, FindingCollector $findings): void
    {
        if (!$node instanceof Expr\StaticPropertyFetch || !$node->name instanceof Node\VarLikeIdentifier) {
            return;
        }

        $class = $node->class instanceof Node\Name ? $node->class->toString() : '...';
        $subject = 'static property ' . $class . '::$' . $node->name->toString();
        $findings->access($isWrite, $subject, Category::STATIC_PROPERTY, $node);
    }
}
