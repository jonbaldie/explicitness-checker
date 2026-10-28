<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;

/**
 * A call that invokes a named function through a literal callable: a string
 * callee, `('name')(...)`; an immediate call of a first-class callable,
 * `(name(...))(...)`; or call_user_func of a string literal,
 * `call_user_func('name', ...)`; or a pipe into a first-class callable.
 * Each is the direct call `name(...)` with the
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
        if ($this->node instanceof Expr\BinaryOp\Pipe) {
            return $this->pipedCall($this->node);
        }
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

    protected function pipedCall(Expr\BinaryOp\Pipe $pipe): ?Expr\FuncCall
    {
        $callable = $pipe->right;
        if ($callable instanceof Expr\FuncCall && $callable->name instanceof Node\Name && $callable->isFirstClassCallable()) {
            return new Expr\FuncCall($callable->name, [new Node\Arg($pipe->left)]);
        }

        return null;
    }

    /**
     * The statically known function name the node invokes, whether called
     * directly by name, through a string callee, or through an immediate
     * first-class callable. Null when the node calls no statically named
     * function, or when it calls one through call_user_func, which passes
     * arguments by value rather than forwarding references.
     */
    public function referenceForwardingFunction(): ?string
    {
        if (!$this->node instanceof Expr\FuncCall || $this->node->isFirstClassCallable()) {
            return null;
        }
        if ($this->node->name instanceof Node\Name) {
            if ($this->callback($this->node) !== null) {
                return null;
            }

            return $this->node->name->toString();
        }

        $directCall = $this->directCall();
        if ($directCall !== null && $directCall->name instanceof Node\Name) {
            return $directCall->name->toString();
        }

        return null;
    }

    /**
     * Whether the call is call_user_func.
     */
    public function isCallUserFunc(): bool
    {
        return $this->node instanceof Expr\FuncCall && $this->callback($this->node) !== null;
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
