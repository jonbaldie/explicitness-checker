<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Scope;

use PhpParser\Node;
use PhpParser\NodeTraverser;

/**
 * Finds the function-likes of a file that the checker reports on: every
 * function and method with a body wherever it's declared (inside conditional
 * blocks, anonymous classes, other function-likes, with or without a
 * namespace), plus every closure and arrow function. Source order.
 *
 * Resolved-AST contract: the AST must have php-parser's NameResolver run over
 * it first, because detectors match names as PHP resolves them. On an
 * unresolved AST, imported function calls are mistaken for built-ins and
 * namespaced static properties are named with their source spelling, with no
 * error. SourceChecker owns the resolution; PHPStan's parser supplies
 * already-resolved nodes to the PHPStan rule. To check source or an
 * unresolved AST, use SourceChecker.
 */
class FunctionLikeFinder
{
    /**
     * @param array<Node> $ast
     *
     * @return list<CheckedFunctionLike>
     */
    public function find(array $ast): array
    {
        $collector = new FunctionLikeCollector();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($collector);
        $traverser->traverse($ast);

        return $collector->getFound();
    }
}
