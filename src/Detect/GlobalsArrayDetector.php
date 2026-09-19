<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\FindingCollector;
use JonBaldie\ExplicitnessChecker\VariableName;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;

/**
 * Reads and writes of `$GLOBALS[...]`, naming the key when it is a literal or
 * a plain variable.
 */
class GlobalsArrayDetector implements Detector
{
    /**
     * Whether the node is `$GLOBALS[...]` (with any dimension, or none).
     *
     * @phpstan-assert-if-true Expr\ArrayDimFetch $node
     */
    public static function isGlobalsFetch(Node $node): bool
    {
        return $node instanceof Expr\ArrayDimFetch
            && $node->var instanceof Expr\Variable
            && $node->var->name === 'GLOBALS';
    }

    public function detect(Node $node, bool $isWrite, FindingCollector $findings): void
    {
        if (!self::isGlobalsFetch($node)) {
            return;
        }

        $key = $this->keyToString($node->dim);
        $subject = $key === null ? '$GLOBALS' : '$GLOBALS[' . $key . ']';
        $findings->access($isWrite, $subject, Category::GLOBALS_ARRAY, $node);
    }

    protected function keyToString(?Expr $dim): ?string
    {
        if ($dim instanceof Scalar\String_) {
            return "'" . $dim->value . "'";
        }
        if ($dim instanceof Scalar\Int_) {
            return (string) $dim->value;
        }
        $name = VariableName::of($dim);

        return $name === null ? null : '$' . $name;
    }
}
