<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker;

use JonBaldie\ExplicitnessChecker\Detect\DetectorSet;
use JonBaldie\ExplicitnessChecker\Scope\BindingsCollector;
use JonBaldie\ExplicitnessChecker\Scope\ReferenceAliases;
use JonBaldie\ExplicitnessChecker\Walk\AccessRules;
use JonBaldie\ExplicitnessChecker\Walk\BodyWalker;
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
    /** Shared by every analysis, so each built-in is reflected once. */
    protected AccessRules $rules;

    public function __construct()
    {
        $this->rules = new AccessRules();
    }

    public function analyse(Node\FunctionLike $node, Mode $mode): FunctionAnalysis
    {
        $bindings = (new BindingsCollector())->collect($node);

        $findings = new FindingCollector();
        $aliases = new ReferenceAliases();
        $walker = new BodyWalker(
            (new DetectorSet())->select($bindings, $mode, $aliases),
            $this->rules,
            $findings,
            $aliases,
        );
        foreach ($node->getStmts() ?? [] as $stmt) {
            $walker->walk($stmt, false);
        }

        return new FunctionAnalysis(
            $findings->findings(),
            $bindings->getParameters(),
            $bindings->getDeclaredGlobals(),
        );
    }
}
