<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;

/**
 * A call that invokes a named function through a literal callable: a string
 * callee, `('name')(...)`; an immediate call of a first-class callable,
 * `(name(...))(...)`; or call_user_func of a string literal,
 * `call_user_func('name', ...)`. Each is the direct call `name(...)` with the
 * invocation's arguments. A callable held in a variable names no function,
 * and a first-class callable that is only created calls nothing.
 */
class LiteralCallable
{
    public function __construct(protected Node $node)
    {
    }

    /**
     * The direct call the node makes, named as written less one leading "\",
     * or null when it invokes no literal callable.
     */
    public function directCall(): ?Expr\FuncCall
    {
        if (!$this->node instanceof Expr\FuncCall || $this->node->isFirstClassCallable()) {
            return null;
        }
        $callback = $this->callback($this->node);
        $callable = $this->node->name instanceof Node\Name ? $callback?->value : $this->node->name;
        $name = $callable === null ? null : $this->callableName($callable);
        if ($name === null) {
            return null;
        }
        $args = array_values(array_filter(
            $this->node->args,
            static fn (Node $arg): bool => $arg !== $callback,
        ));

        return new Expr\FuncCall(new Node\Name($name), $args, $this->node->getAttributes());
    }

    /**
     * call_user_func's callback argument: the first positional one, or the
     * one named callback. Null for any other call.
     */
    protected function callback(Expr\FuncCall $call): ?Node\Arg
    {
        if (!$call->name instanceof Node\Name || $call->name->toLowerString() !== 'call_user_func') {
            return null;
        }
        foreach ($call->args as $index => $arg) {
            if ($arg instanceof Node\Arg && !$arg->unpack && ($arg->name === null ? $index === 0 : $arg->name->toString() === 'callback')) {
                return $arg;
            }
        }

        return null;
    }

    protected function callableName(Expr $callable): ?string
    {
        if ($callable instanceof Scalar\String_) {
            $name = str_starts_with($callable->value, '\\') ? substr($callable->value, 1) : $callable->value;

            return $name === '' ? null : $name;
        }
        if ($callable instanceof Expr\FuncCall && $callable->name instanceof Node\Name && $callable->isFirstClassCallable()) {
            return $callable->name->toString();
        }

        return null;
    }
}
