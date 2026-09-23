<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * A destructuring target that binds an item by reference (`[&$x]`,
 * `[$k, [&$v]]`) hands out a writable reference to the value it destructures.
 */
class ReferenceDestructuring
{
    public static function bindsReference(Node $target): bool
    {
        if (!$target instanceof Expr\List_) {
            return false;
        }
        foreach ($target->items as $item) {
            if ($item !== null && ($item->byRef || self::bindsReference($item->value))) {
                return true;
            }
        }

        return false;
    }
}
