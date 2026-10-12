<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\FindingCollector;
use PhpParser\Node;
use PhpParser\Node\Expr;

class StaticCallDetector implements Detector
{
    public function detect(Node $node, bool $isWrite, FindingCollector $findings): FindingCollector
    {
        if (
            !$node instanceof Expr\StaticCall
            || $node->isFirstClassCallable()
            || ($node->args !== [] && !$this->isFacade($node->class))
            || ($node->class instanceof Node\Name && $node->class->isSpecialClassName())
        ) {
            return $findings;
        }

        $class = $node->class instanceof Node\Name ? $node->class->toString() : '...';
        $method = $node->name instanceof Node\Identifier ? $node->name->toString() : '...';

        $subject = $class . '::' . $method . '()';
        $findings = $findings->input('may read via static method ' . $subject, Category::STATIC_CALL, $node);
        $findings = $findings->output('may write via static method ' . $subject, Category::STATIC_CALL, $node);
        return $findings;
    }

    protected function isFacade(Node\Name|Expr $class): bool
    {
        if (!$class instanceof Node\Name) {
            return false;
        }
        $name = strtolower($class->toString());

        return str_starts_with($name, 'illuminate\\support\\facades\\')
            || str_starts_with($name, 'facades\\');
    }

}
