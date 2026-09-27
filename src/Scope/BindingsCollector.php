<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Scope;

use JonBaldie\ExplicitnessChecker\VariableName;
use JonBaldie\ExplicitnessChecker\Walk\GlobalDeclarations;
use JonBaldie\ExplicitnessChecker\Walk\StaticDeclarations;
use PhpParser\Node;

/**
 * Builds the Bindings of one function-like from its parameters, its
 * by-reference closure captures and the declarations in its body.
 */
class BindingsCollector
{
    public function collect(Node\FunctionLike $node): Bindings
    {
        $stmts = $node->getStmts() ?? [];
        $globals = (new GlobalDeclarations())->collectWithDynamic($stmts);

        return new Bindings(
            $this->parameterNames($node, false),
            $this->parameterNames($node, true),
            $globals['names'],
            $globals['hasDynamicName'],
            (new StaticDeclarations())->collect($stmts),
            $this->capturedReferenceNames($node),
        );
    }

    /**
     * @return list<string>
     */
    protected function parameterNames(Node\FunctionLike $node, bool $byReferenceOnly): array
    {
        $names = [];
        foreach ($node->getParams() as $param) {
            $name = VariableName::of($param->var);
            if ($name !== null && ($param->byRef || !$byReferenceOnly)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * The names a closure captures by reference with `use (&$x)`.
     *
     * @return list<string>
     */
    protected function capturedReferenceNames(Node\FunctionLike $node): array
    {
        if (!$node instanceof Node\Expr\Closure) {
            return [];
        }

        $names = [];
        foreach ($node->uses as $use) {
            $name = VariableName::of($use->var);
            if ($use->byRef && $name !== null) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
