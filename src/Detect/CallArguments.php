<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;

/**
 * What the arguments of a call, `new` expression or exit construct say, from
 * literals only. A parameter is found by position or by name; the exit
 * construct's one optional expression is its first positional argument.
 */
class CallArguments
{
    public function __construct(protected Expr\FuncCall|Expr\New_|Expr\Exit_ $node)
    {
    }

    public function isEmpty(): bool
    {
        return $this->args() === [];
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
     * The literal string passed for a parameter, or null when none is.
     */
    public function string(int $position, string $name): ?string
    {
        $value = $this->value($position, $name);

        return $value instanceof Scalar\String_ ? $value->value : null;
    }

    /**
     * The value passed for a parameter, or null when none is.
     */
    protected function value(int $position, string $name): ?Expr
    {
        foreach ($this->args() as $index => $arg) {
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
     * @return array<Node\Arg|Node\VariadicPlaceholder|Node\ArgPlaceholder>
     */
    protected function args(): array
    {
        if (!$this->node instanceof Expr\Exit_) {
            return $this->node->args;
        }

        return $this->node->expr === null ? [] : [new Node\Arg($this->node->expr)];
    }

    /**
     * The value is the constant named, in any case.
     */
    protected function isConstant(?Expr $value, string $name): bool
    {
        return $value instanceof Expr\ConstFetch && $value->name->toLowerString() === $name;
    }
}
