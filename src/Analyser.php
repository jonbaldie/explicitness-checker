<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker;

use JonBaldie\ExplicitnessChecker\Detect\DetectorSet;
use JonBaldie\ExplicitnessChecker\Scope\BindingsBuilder;
use JonBaldie\ExplicitnessChecker\Walk\AccessRules;
use JonBaldie\ExplicitnessChecker\Walk\BodyWalker;
use JonBaldie\ExplicitnessChecker\Walk\ReferenceAliases;
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
        $bindings = (new BindingsBuilder())->build($node);

        $findings = new FindingCollector();
        $aliases = new ReferenceAliases();
        $walker = new BodyWalker(
            (new DetectorSet())->select(
                $bindings,
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

        return new FunctionAnalysis(
            $findings->inputs(),
            $findings->outputs(),
            $bindings->getParameters(),
            $bindings->getDeclaredGlobals(),
            $bindings,
        );
    }
}
