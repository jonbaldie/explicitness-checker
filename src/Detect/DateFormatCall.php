<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Category;
use PhpParser\Node;
use PhpParser\Node\Expr;

final class DateFormatCall
{
    /** @return array{string, list<array{string, bool, string}>}|null */
    public static function classify(?Node $node): ?array
    {
        if (!$node instanceof Expr\StaticCall || !$node->class instanceof Node\Name || !$node->name instanceof Node\Identifier || $node->isFirstClassCallable()) {
            return null;
        }
        if (!in_array(strtolower($node->class->toString()), ['datetime', 'datetimeimmutable'], true) || strtolower($node->name->toString()) !== 'createfromformat') {
            return null;
        }
        $subject = $node->class->toString() . '::' . $node->name->toString();
        $entries = (new CallArguments($node))->formatReadsClock() ? [[Category::TIME, false, 'reads system time']] : [];

        return [$subject, $entries];
    }
}
