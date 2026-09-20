<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Scope;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

/**
 * Node visitor behind FunctionLikeFinder: records every function-like with a
 * body, in traversal (source) order, tracking the namespace, the innermost
 * class-like and (through HookedProperties) the innermost hooked property for
 * naming. Use one instance per traversal.
 */
class FunctionLikeCollector extends NodeVisitorAbstract
{
    protected string $namespace = '';

    /** @var list<string|null> enclosing class-likes, innermost last; null is anonymous */
    protected array $classes = [];

    /** @var list<CheckedFunctionLike> */
    protected array $found = [];

    protected HookedProperties $properties;

    public function __construct()
    {
        $this->properties = new HookedProperties();
    }

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Stmt\Namespace_) {
            $this->namespace = $node->name === null ? '' : $node->name->toString();
        }
        if ($node instanceof Stmt\ClassLike) {
            $this->classes[] = $this->classNameOf($node);
        }
        $this->properties->enter($node);
        if ($node instanceof Node\FunctionLike && $node->getStmts() !== null) {
            $this->found[] = new CheckedFunctionLike($node, $this->nameOf($node));
        }

        return null;
    }

    public function leaveNode(Node $node): ?int
    {
        if ($node instanceof Stmt\ClassLike) {
            array_pop($this->classes);
        }
        $this->properties->leave($node);

        return null;
    }

    /**
     * @return list<CheckedFunctionLike>
     */
    public function getFound(): array
    {
        return $this->found;
    }

    /**
     * Fully qualified class-like name, or null for an anonymous class.
     *
     * Asks isAnonymous() rather than checking for a null name: PHPStan's
     * parser gives anonymous classes a generated name (containing a path
     * hash) and overrides isAnonymous() to keep reporting them as anonymous.
     */
    protected function classNameOf(Stmt\ClassLike $node): ?string
    {
        $name = $node->name;
        if ($name === null || ($node instanceof Stmt\Class_ && $node->isAnonymous())) {
            return null;
        }

        return FunctionLikeNames::qualify($this->namespace, $name->toString());
    }

    protected function nameOf(Node\FunctionLike $node): string
    {
        if ($node instanceof Stmt\Function_) {
            return FunctionLikeNames::qualify($this->namespace, $node->name->toString());
        }
        if ($node instanceof Stmt\ClassMethod) {
            return FunctionLikeNames::method(end($this->classes) ?: null, $node->name->toString());
        }

        return $this->properties->hookName($node, end($this->classes) ?: null) ?? FunctionLikeNames::CLOSURE;
    }
}
