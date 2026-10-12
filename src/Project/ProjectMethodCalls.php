<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Project;

use PhpParser\Node;
use PhpParser\Node\Expr;

final class ProjectMethodCalls
{
    public static function target(Node $node, ?string $class, ProjectIndex $index): ?ProjectFunction
    {
        if ($class === null) {
            return null;
        }
        if ($node instanceof Expr\StaticCall) {
            return self::staticTarget($node, $class, $index);
        }
        if ($node instanceof Expr\MethodCall || $node instanceof Expr\NullsafeMethodCall) {
            return self::objectTarget($node, $class, $index);
        }

        return null;
    }

    protected static function staticTarget(Expr\StaticCall $node, string $class, ProjectIndex $index): ?ProjectFunction
    {
        if (!$node->class instanceof Node\Name || !$node->class->isSpecialClassName() || !$node->name instanceof Node\Identifier || $node->isFirstClassCallable()) {
            return null;
        }
        if (strtolower($node->class->toString()) === 'parent') {
            $class = $index->parentOf($class);
        }

        return $index->method($class, $node->name->toString());
    }

    protected static function objectTarget(Expr\MethodCall|Expr\NullsafeMethodCall $node, string $class, ProjectIndex $index): ?ProjectFunction
    {
        if (!$node->var instanceof Expr\Variable || $node->var->name !== 'this' || !$node->name instanceof Node\Identifier || $node->isFirstClassCallable()) {
            return null;
        }

        return $index->method($class, $node->name->toString());
    }
}
