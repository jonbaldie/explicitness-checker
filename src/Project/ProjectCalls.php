<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Project;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

final class ProjectCalls
{
    /** @return array<string, CallEdge> immediate callees, first call-site retained */
    public static function from(ProjectFunction $function, ProjectIndex $index): array
    {
        $edges = [];
        foreach ($function->getNode()->getStmts() ?? [] as $statement) {
            $edges += self::scan($statement, $function->getClass(), $index);
        }

        return $edges;
    }

    /** @return array<string, CallEdge> */
    protected static function scan(Node $node, ?string $class, ProjectIndex $index): array
    {
        if ($node instanceof Node\FunctionLike || $node instanceof Stmt\ClassLike) {
            return [];
        }
        $edges = [];
        $callee = self::target($node, $class, $index);
        if ($callee !== null) {
            $edges[$callee->getId()] = new CallEdge($callee, $node->getStartLine());
        }
        foreach (NodeChildren::of($node) as $child) {
            $edges += self::scan($child, $class, $index);
        }

        return $edges;
    }
    protected static function target(Node $node, ?string $class, ProjectIndex $index): ?ProjectFunction
    {
        if ($node instanceof Expr\FuncCall && $node->name instanceof Node\Name && !$node->isFirstClassCallable()) {
            return $index->callee($node->name);
        }

        return ProjectMethodCalls::target($node, $class, $index);
    }

}
