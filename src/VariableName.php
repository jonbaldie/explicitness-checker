<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker;

use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * The name of a plain variable such as `$name`, without the "$".
 */
class VariableName
{
    /**
     * Null unless the node is a variable with a literal name: variable-variables
     * (`$$name`, `${expr}`) and every other node have none.
     */
    public static function of(?Node $node): ?string
    {
        if ($node instanceof Expr\Variable && is_string($node->name)) {
            return $node->name;
        }

        return null;
    }
}
