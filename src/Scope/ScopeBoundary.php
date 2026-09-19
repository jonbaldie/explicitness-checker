<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Scope;

use PhpParser\Node;
use PhpParser\Node\Stmt;

/**
 * Nodes that open a scope of their own: function-likes (functions, methods,
 * closures, arrow functions) and class-likes (including anonymous classes).
 *
 * Analysing a function-like never descends into a boundary found in its body;
 * each nested function-like is checked on its own.
 */
class ScopeBoundary
{
    public static function opensScope(Node $node): bool
    {
        return $node instanceof Node\FunctionLike || $node instanceof Stmt\ClassLike;
    }
}
