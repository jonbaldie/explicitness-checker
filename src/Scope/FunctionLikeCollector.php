<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Scope;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

/**
 * Node visitor behind FunctionLikeFinder: records every function-like with a
 * body, in traversal (source) order, tracking the namespace and the innermost
 * class-like for naming. Use one instance per traversal.
 */
class FunctionLikeCollector extends NodeVisitorAbstract
{
    protected string $namespace = '';

    /** @var list<string|null> enclosing class-likes, innermost last; null is anonymous */
    protected array $classes = [];

    /** @var list<CheckedFunctionLike> */
    protected array $found = [];

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Stmt\Namespace_) {
            $this->namespace = $node->name === null ? '' : $node->name->toString();
        }
        if ($node instanceof Stmt\ClassLike) {
            $this->classes[] = $node->name === null
                ? null
                : FunctionLikeNames::qualify($this->namespace, $node->name->toString());
        }
        if ($node instanceof Node\FunctionLike && $node->getStmts() !== null) {
            $this->found[] = new CheckedFunctionLike($node, $this->nameOf($node));
        }

        return null;
    }

    public function leaveNode(Node $node): ?int
    {
        if ($node instanceof Stmt\Namespace_) {
            $this->namespace = '';
        }
        if ($node instanceof Stmt\ClassLike) {
            array_pop($this->classes);
        }

        return null;
    }

    /**
     * @return list<CheckedFunctionLike>
     */
    public function getFound(): array
    {
        return $this->found;
    }

    protected function nameOf(Node\FunctionLike $node): string
    {
        if ($node instanceof Stmt\Function_) {
            return FunctionLikeNames::qualify($this->namespace, $node->name->toString());
        }
        if ($node instanceof Stmt\ClassMethod) {
            return FunctionLikeNames::method(end($this->classes) ?: null, $node->name->toString());
        }

        return FunctionLikeNames::CLOSURE;
    }
}
