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
