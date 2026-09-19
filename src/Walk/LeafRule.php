<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use JonBaldie\ExplicitnessChecker\Detect\GlobalsArrayDetector;
use JonBaldie\ExplicitnessChecker\Scope\ScopeBoundary;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Nodes whose children the walk never visits:
 * - variables (a variable-variable's name expression is not walked),
 * - `$GLOBALS[...]` fetches (reported as a whole, the dimension is not walked),
 * - `global` statements (a declaration is neither a read nor a write),
 * - nested scopes (closures, arrow functions, nested functions, anonymous and
 *   nested classes), which are checked as function-likes of their own.
 */
class LeafRule implements ChildAccessRule
{
    public function children(Node $node, bool $isWrite): ?array
    {
        if (
            $node instanceof Expr\Variable
            || ScopeBoundary::opensScope($node)
            || $node instanceof Stmt\Global_
            || GlobalsArrayDetector::isGlobalsFetch($node)
        ) {
            return [];
        }

        return null;
    }
}
