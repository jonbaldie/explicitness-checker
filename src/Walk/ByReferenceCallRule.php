<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * `sort($items)`: an argument a built-in takes by reference is read, then
 * written. Which positions are by reference comes from ByReferenceParameters.
 * User-defined functions, undefined functions and calls with unpacked
 * arguments get no marking.
 */
class ByReferenceCallRule implements ChildAccessRule
{
    public function __construct(protected ByReferenceParameters $parameters)
    {
    }

    public function children(Node $node, bool $isWrite): ?array
    {
        if (
            !$node instanceof Expr\FuncCall
            || !$node->name instanceof Node\Name
            || !$this->parameters->isBuiltin($node->name->toString())
            || $this->hasUnpackedArgument($node)
        ) {
            return null;
        }

        $function = $node->name->toString();
        $children = [[$node->name, $isWrite]];
        foreach ($node->args as $position => $arg) {
            if ($arg instanceof Node\Arg && $this->parameters->isByReference($function, $position, $arg->name?->toString())) {
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
}
