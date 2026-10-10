<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;

/**
 * What the arguments of a call, `new` expression, exit construct or pipe say,
 * from literals only. A parameter is found by position or by name; the exit
 * construct's one optional expression, and the value a pipe passes, is its
 * first positional argument.
 */
class CallArguments
{
    public function __construct(protected Expr\FuncCall|Expr\New_|Expr\Exit_|Expr\BinaryOp\Pipe $node)
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

    /**
     * The parameter is left out, passed the literal null, or passed the
     * literal 0.
     */
    public function omitsOrZero(int $position, string $name): bool
    {
        $value = $this->value($position, $name);

        return $value === null
            || $this->isConstant($value, 'null')
            || ($value instanceof Scalar\Int_ && $value->value === 0);
    }

    /**
     * At least one of the parameters, named in positional order, is omitted.
     *
     * @param list<string> $names
     */
    public function omitsAny(array $names): bool
    {
        foreach ($names as $position => $name) {
            if ($this->omits($position, $name)) {
                return true;
            }
        }

        return false;
    }

    public function isTrue(int $position, string $name): bool
    {
        return $this->isConstant($this->value($position, $name), 'true');
    }

    /**
     * A date is built from the clock when its datetime argument is absent,
     * null, 'now' in any case, or '', which PHP also reads as now.
     */
    public function readsClock(int $position = 0, string $name = 'datetime'): bool
    {
        $datetime = $this->string($position, $name);

        return $this->omits($position, $name)
            || ($datetime !== null && in_array(strtolower($datetime), ['now', ''], true));
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
        if ($this->node instanceof Expr\BinaryOp\Pipe) {
            return [new Node\Arg($this->node->left)];
        }
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
