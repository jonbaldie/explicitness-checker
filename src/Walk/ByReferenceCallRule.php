<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * `sort($items)`: an argument a built-in takes by reference is read, then
 * written. Which positions are by reference comes from reflection, so it
 * depends on the extensions loaded. User-defined functions, undefined
 * functions and calls with unpacked arguments get no marking.
 */
class ByReferenceCallRule implements ChildAccessRule
{
    public function children(Node $node, bool $isWrite): ?array
    {
        if (
            !$node instanceof Expr\FuncCall
            || !$node->name instanceof Node\Name
            || !function_exists($node->name->toString())
        ) {
            return null;
        }

        $function = new \ReflectionFunction($node->name->toString());
        if (!$function->isInternal() || $this->hasUnpackedArgument($node)) {
            return null;
        }

        $parameters = $function->getParameters();
        $children = [[$node->name, $isWrite]];
        foreach ($node->args as $position => $arg) {
            if ($this->parameter($parameters, $position, $arg)?->isPassedByReference() === true) {
                $children[] = [$arg, false];
                $children[] = [$arg, true];
                continue;
            }
            $children[] = [$arg, $isWrite];
        }

        return $children;
    }

    protected function hasUnpackedArgument(Expr\FuncCall $node): bool
    {
        foreach ($node->args as $arg) {
            if ($arg instanceof Node\Arg && $arg->unpack) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<\ReflectionParameter> $parameters
     */
    protected function parameter(array $parameters, int $position, Node $arg): ?\ReflectionParameter
    {
        if (!$arg instanceof Node\Arg) {
            return null;
        }
        if ($arg->name === null) {
            $last = end($parameters);

            return $parameters[$position] ?? ($last !== false && $last->isVariadic() ? $last : null);
        }
        foreach ($parameters as $parameter) {
            if ($parameter->getName() === $arg->name->toString()) {
                return $parameter;
            }
        }

        return null;
    }
}
