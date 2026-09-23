<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;

/**
 * What the arguments of a call or `new` expression say, from literals only.
 * A parameter is found by position or by name.
 */
class CallArguments
{
    public function __construct(protected Expr\FuncCall|Expr\New_ $node)
    {
    }

    public function isEmpty(): bool
    {
        return $this->node->args === [];
    }

    /**
     * The parameter is left out or passed the literal null, so PHP falls back
     * to its default.
     */
    public function omits(int $position, string $name): bool
    {
        $value = $this->value($position, $name);

        return $value === null || $this->isConstant($value, 'null');
    }

    public function isTrue(int $position, string $name): bool
    {
        return $this->isConstant($this->value($position, $name), 'true');
    }

    /**
     * A date is built from the clock when its datetime argument is absent,
     * null, 'now' in any case, or '', which PHP also reads as now.
     */
    public function readsClock(): bool
    {
        $datetime = $this->value(0, 'datetime');

        return $datetime === null
            || $this->isConstant($datetime, 'null')
            || ($datetime instanceof Scalar\String_ && in_array(strtolower($datetime->value), ['now', ''], true));
    }

    /**
     * The value passed for a parameter, or null when none is.
     */
    protected function value(int $position, string $name): ?Expr
    {
        foreach ($this->node->args as $index => $arg) {
            if (!$arg instanceof Node\Arg) {
                continue;
            }
            if ($arg->name === null ? $index === $position : $arg->name->toString() === $name) {
                return $arg->value;
            }
        }

        return null;
    }

    /**
     * The value is the constant named, in any case.
     */
    protected function isConstant(?Expr $value, string $name): bool
    {
        return $value instanceof Expr\ConstFetch && $value->name->toLowerString() === $name;
    }
}
