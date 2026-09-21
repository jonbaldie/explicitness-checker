<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker;

use JonBaldie\ExplicitnessChecker\Detect\DetectorSet;
use JonBaldie\ExplicitnessChecker\Walk\AccessRules;
use JonBaldie\ExplicitnessChecker\Walk\BodyWalker;
use JonBaldie\ExplicitnessChecker\Walk\GlobalDeclarations;
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
        $declaredGlobals = (new GlobalDeclarations())->collect($stmts);

        $findings = new FindingCollector();
        $walker = new BodyWalker(
            (new DetectorSet())->select($parameters, $declaredGlobals, $mode),
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
            $name = VariableName::of($param->var);
            if ($name !== null) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
