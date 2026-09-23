<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\FindingCollector;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Strict mode: constructing an object that reads the clock (a date from
 * 'now') or the default random engine (a Randomizer given no engine). The
 * class is matched by its resolved name, in any case.
 */
class NewExpressionDetector implements Detector
{
    public function detect(Node $node, bool $isWrite, FindingCollector $findings): void
    {
        if (!$node instanceof Expr\New_ || !$node->class instanceof Node\Name) {
            return;
        }

        $class = $node->class->toString();
        $arguments = new CallArguments($node);
        $finding = match (strtolower($class)) {
            'datetime', 'datetimeimmutable' => $arguments->readsClock() ? ['reads system time', Category::TIME] : null,
            'random\randomizer' => $arguments->isEmpty() ? ['reads from random number generator', Category::RANDOM] : null,
            default => null,
        };
        if ($finding !== null) {
            $findings->input($finding[0] . ' (' . $class . ')', $finding[1], $node);
        }
    }
}
