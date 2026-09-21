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
 * The AST is expected to have php-parser's NameResolver run over it first:
 * detectors match names as PHP resolves them, so an unresolved AST yields
 * false positives for imported function calls and source-spelled names for
 * namespaced static properties, with no error. SourceChecker owns the
 * resolution; the PHPStan rule receives already-resolved nodes from PHPStan's
 * parser. To check source or an unresolved AST, use SourceChecker.
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
