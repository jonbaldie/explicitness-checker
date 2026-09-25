<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker;

use JonBaldie\ExplicitnessChecker\Detect\DetectorSet;
use JonBaldie\ExplicitnessChecker\Walk\AccessRules;
use JonBaldie\ExplicitnessChecker\Walk\BodyWalker;
use JonBaldie\ExplicitnessChecker\Walk\GlobalDeclarations;
use JonBaldie\ExplicitnessChecker\Walk\ReferenceAliases;
use JonBaldie\ExplicitnessChecker\Walk\StaticDeclarations;
use PhpParser\Node;

/**
 * Finds the implicit inputs and outputs of one function-like node.
 *
 * Shared by the CLI and the PHPStan rule. Purely syntactic, prints nothing.
 *
 * Resolved-AST contract: the node must come from an AST that php-parser's
 * NameResolver has run over, as FunctionLikeFinder's contract requires. To
 * check source or an unresolved AST, use SourceChecker, which resolves names
 * for you.
 */
class Analyser
{
    public function analyse(Node\FunctionLike $node, Mode $mode): FunctionAnalysis
    {
        $stmts = $node->getStmts() ?? [];
        $parameters = $this->parameterNames($node);
        $globals = (new GlobalDeclarations())->collectWithDynamic($stmts);
        $declaredGlobals = $globals['names'];
        $staticVariables = (new StaticDeclarations())->collect($stmts);

        $findings = new FindingCollector();
        $aliases = new ReferenceAliases();
        $walker = new BodyWalker(
            (new DetectorSet())->select(
                $parameters,
                $this->byReferenceParameterNames($node),
                $declaredGlobals,
                $globals['hasDynamicName'],
                $staticVariables,
                $this->capturedReferenceNames($node),
                $mode,
                $aliases,
            ),
            new AccessRules(),
            $findings,
            $aliases,
        );
        foreach ($stmts as $stmt) {
            $walker->walk($stmt, false);
        }

        return new FunctionAnalysis($findings->inputs(), $findings->outputs(), $parameters, $declaredGlobals);
    }

    /**
     * @return list<string>
     */
    protected function parameterNames(Node\FunctionLike $node): array
    {
        $names = [];
        foreach ($node->getParams() as $param) {
            $name = VariableName::of($param->var);
            if ($name !== null) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    protected function byReferenceParameterNames(Node\FunctionLike $node): array
    {
        $names = [];
        foreach ($node->getParams() as $param) {
            $name = VariableName::of($param->var);
            if ($param->byRef && $name !== null) {
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
