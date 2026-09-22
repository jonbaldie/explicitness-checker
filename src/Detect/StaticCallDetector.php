<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\FindingCollector;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Default mode: `ClassName::method()` calls with no arguments, whose result
 * can only come from outside the function's arguments. Calls on the current
 * class (self::, parent::, static::) are not reported.
 */
class StaticCallDetector implements Detector
{
    public function detect(Node $node, bool $isWrite, FindingCollector $findings): void
    {
        if (
            !$node instanceof Expr\StaticCall
            || $node->args !== []
            || !$node->class instanceof Node\Name
            || $node->class->isSpecialClassName()
            || !$node->name instanceof Node\Identifier
        ) {
            return;
        }

        $subject = $node->class->toString() . '::' . $node->name->toString() . '()';
        $findings->input('read from static method ' . $subject, Category::STATIC_CALL, $node);
    }
}
