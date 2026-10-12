<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Scope;

use PhpParser\Node;
use PhpParser\Node\Stmt;

/**
 * Immutable discovery state behind FunctionLikeFinder: records each function-like with a
 * body, in traversal (source) order, tracking the namespace, the innermost
 * class-like and (through HookedProperties) the innermost hooked property for
 * naming. Each enter/leave returns the next snapshot.
 */
class FunctionLikeCollector
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

    public function enter(Node $node): self
    {
        $next = clone $this;
        if ($node instanceof Stmt\Namespace_) {
            $next->namespace = $node->name === null ? '' : $node->name->toString();
        }
        if ($node instanceof Stmt\ClassLike) {
            $next->classes[] = $next->classNameOf($node);
        }
        $next->properties = $next->properties->enter($node);
        if ($node instanceof Node\FunctionLike && $node->getStmts() !== null) {
            $next->found[] = new CheckedFunctionLike($node, $next->nameOf($node));
        }

        return $next;
    }

    public function leave(Node $node): self
    {
        $next = clone $this;
        if ($node instanceof Stmt\ClassLike) {
            array_pop($next->classes);
        }
        $next->properties = $next->properties->leave($node);

        return $next;
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
            return FunctionLikeNames::method($this->className(), $node->name->toString());
        }

        return $this->properties->hookName($node, $this->className()) ?? FunctionLikeNames::CLOSURE;
    }

    /**
     * The innermost class context, or the nearest named class for an
     * anonymous class nested inside one.
     */
    protected function className(): ?string
    {
        $className = $this->classes[array_key_last($this->classes)] ?? null;
        if ($className !== null) {
            return $className;
        }

        foreach (array_reverse($this->classes) as $enclosingClassName) {
            if ($enclosingClassName !== null) {
                return FunctionLikeNames::nestedAnonymousClass($enclosingClassName);
            }
        }

        return null;
    }
}
