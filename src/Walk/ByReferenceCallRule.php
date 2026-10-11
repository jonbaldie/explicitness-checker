<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use JonBaldie\ExplicitnessChecker\LiteralCallable;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * `sort($items)`: an argument a built-in takes by reference is read, then
 * written. Which positions are by reference comes from ByReferenceParameters,
 * by default reflected on the running PHP once per function, so it depends on
 * the extensions loaded. User-defined functions, undefined functions, calls
 * with unpacked arguments and non-reference-forwarding wrappers get no marking.
 */
class ByReferenceCallRule implements ChildAccessRule
{
    protected ByReferenceParameters $byReference;

    public function __construct(?ByReferenceParameters $byReference = null)
    {
        $this->byReference = $byReference ?? new ReflectedByReferenceParameters();
    }

    public function children(Node $node, bool $isWrite): ?array
    {
        if (
            !$node instanceof Expr\FuncCall
            || $this->hasUnpackedArgument($node)
        ) {
            return null;
        }

        $function = (new LiteralCallable($node))->referenceForwardingFunction();
        if ($function === null || !$this->byReference->isBuiltin($function)) {
            return null;
        }

        $children = [[$node->name, $isWrite]];
        foreach ($node->args as $position => $arg) {
            if ($arg instanceof Node\Arg && $this->byReference->isPassedByReference($function, $position, $arg->name?->toString())) {
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
