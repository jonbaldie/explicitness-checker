<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Scope;

use PhpParser\Node;
use PhpParser\Node\Expr;

/** How selection and shallow copying change an ordinary value's caller origins. */
class ValueSelections
{
    /** @return array<string, string> */
    public function of(Node $node, ValueOrigins $values): array
    {
        if ($node instanceof Expr\Clone_) {
            return array_fill_keys(array_keys($values->of($node->expr)), ValueOrigins::FRESH_OBJECT);
        }
        if ($node instanceof Expr\Assign) {
            return $values->of($node->expr);
        }
        if ($node instanceof Expr\PropertyFetch || $node instanceof Expr\NullsafePropertyFetch) {
            return self::selected($values->of($node->var), false);
        }
        if ($node instanceof Expr\ArrayDimFetch) {
            return self::selected($values->of($node->var), true);
        }
        if ($node instanceof Expr\Ternary) {
            return self::merge($values->of($node->if ?? $node->cond), $values->of($node->else));
        }
        if ($node instanceof Expr\BinaryOp\Coalesce) {
            return self::merge($values->of($node->left), $values->of($node->right));
        }

        return [];
    }

    /**
     * @param array<string, string> $origins
     * @return array<string, string>
     */
    public static function selected(array $origins, bool $array): array
    {
        foreach ($origins as $origin => $reach) {
            $origins[$origin] = ValueOrigins::SHARED;
            if ($array && $reach === ValueOrigins::OWN_STORAGE) {
                $origins[$origin] = ValueOrigins::OWN_STORAGE;
            }
            if (!$array && $reach === ValueOrigins::FRESH_OBJECT) {
                $origins[$origin] = ValueOrigins::OWN_STORAGE;
            }
        }

        return $origins;
    }

    /**
     * @param array<string, string> $left
     * @param array<string, string> $right
     * @return array<string, string>
     */
    public static function merge(array $left, array $right): array
    {
        foreach ($right as $origin => $reach) {
            $previous = $left[$origin] ?? $reach;
            $left[$origin] = $previous === ValueOrigins::FRESH_OBJECT ? $reach : $previous;
        }

        return $left;
    }
}
