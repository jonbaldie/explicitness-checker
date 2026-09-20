<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Scope;

use PhpParser\Node;
use PhpParser\Node\Stmt;

/**
 * The hooked property declarations enclosing the node being traversed, so that
 * a PHP 8.4 property hook is named after the property it belongs to rather
 * than falling in with closures. Use one instance per traversal, calling
 * enter() and leave() for every node.
 */
class HookedProperties
{
    /** @var list<string> enclosing hooked properties, innermost last */
    protected array $names = [];

    public function enter(Node $node): void
    {
        if ($this->isHooked($node)) {
            $this->names[] = $this->propertyNameOf($node);
        }
    }

    public function leave(Node $node): void
    {
        if ($this->isHooked($node)) {
            array_pop($this->names);
        }
    }

    /**
     * The name to report a property hook under, or null if the node is not a
     * property hook.
     *
     * @param string|null $className fully qualified class name, null for an anonymous class
     */
    public function hookName(Node $node, ?string $className): ?string
    {
        if (!$node instanceof Node\PropertyHook) {
            return null;
        }

        return FunctionLikeNames::hook($className, (string) end($this->names), $node->name->toString());
    }

    /**
     * A property declaration or promoted constructor parameter with hooks:
     * the declaration whose hooks are named after it.
     *
     * @phpstan-assert-if-true Stmt\Property|Node\Param $node
     */
    protected function isHooked(Node $node): bool
    {
        return ($node instanceof Stmt\Property || $node instanceof Node\Param) && $node->hooks !== [];
    }

    /**
     * The name of a hooked property, without its `$`. A hooked property
     * declares exactly one name; a promoted parameter is named after its
     * variable.
     *
     * @param Stmt\Property|Node\Param $node
     */
    protected function propertyNameOf(Node $node): string
    {
        if ($node instanceof Stmt\Property) {
            return $node->props[0]->name->toString();
        }

        $variable = $node->var;

        return $variable instanceof Node\Expr\Variable && is_string($variable->name) ? $variable->name : '';
    }
}
