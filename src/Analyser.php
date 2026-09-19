<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker;

use JonBaldie\ExplicitnessChecker\Detect\DetectorSet;
use JonBaldie\ExplicitnessChecker\Walk\AccessRules;
use JonBaldie\ExplicitnessChecker\Walk\BodyWalker;
use JonBaldie\ExplicitnessChecker\Walk\GlobalDeclarations;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Finds the implicit inputs and outputs of one function-like node.
 *
 * Shared by the CLI and the PHPStan rule. Purely syntactic, prints nothing.
 */
class Analyser
{
    /**
     * @param bool $strict also report output functions, file, time, random,
     *                     environment, header, error-log and session access
     * @param bool $props  also report `$this->prop` and `Class::$prop` access
     */
    public function analyse(Node\FunctionLike $node, bool $strict, bool $props): FunctionAnalysis
    {
        $stmts = $node->getStmts() ?? [];
        $parameters = $this->parameterNames($node);
        $declaredGlobals = (new GlobalDeclarations())->collect($stmts);

        $findings = new FindingCollector();
        $walker = new BodyWalker(
            (new DetectorSet())->select($parameters, $declaredGlobals, $strict, $props),
            new AccessRules(),
            $findings,
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
            if ($param->var instanceof Expr\Variable && is_string($param->var->name)) {
                $names[] = $param->var->name;
            }
        }

        return $names;
    }
}
