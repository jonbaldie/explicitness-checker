<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Scope;

use JonBaldie\ExplicitnessChecker\VariableName;
use PhpParser\Node;

/** Literal names replaced by a binding, including list destructuring. */
class BoundNames
{
    /** @return list<string> */
    public static function of(Node $node): array
    {
        $name = VariableName::of($node);
        if ($name !== null) {
            return [$name];
        }
        if (!$node instanceof \PhpParser\Node\Expr\List_) {
            return [];
        }

        $names = [];
        foreach ($node->items as $item) {
            if ($item !== null) {
                $names = array_merge($names, self::of($item->value));
            }
        }

        return $names;
    }
}
