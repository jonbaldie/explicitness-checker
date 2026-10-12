<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker;

use JonBaldie\ExplicitnessChecker\Detect\DetectorSet;
use JonBaldie\ExplicitnessChecker\Scope\BindingsCollector;
use JonBaldie\ExplicitnessChecker\Scope\Denotation;
use JonBaldie\ExplicitnessChecker\Walk\AccessRules;
use JonBaldie\ExplicitnessChecker\Walk\BodyWalker;
use JonBaldie\ExplicitnessChecker\Walk\ReflectedByReferenceParameters;
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
    public function __construct(protected ?ReflectedByReferenceParameters $parameters = null)
    {
    }

    public function analyse(Node\FunctionLike $node, Mode $mode): FunctionAnalysis
    {
        $bindings = (new BindingsCollector())->collect($node);

        $findings = new FindingCollector();
        $denotation = new Denotation($bindings);
        $parameters = ($this->parameters ?? new ReflectedByReferenceParameters())->forNodes([$node]);
        $constructor = $node instanceof Node\Stmt\ClassMethod && strtolower($node->name->toString()) === '__construct';
        $walker = new BodyWalker($bindings, $mode, new AccessRules($parameters), $parameters, $constructor);
        foreach ($node->getStmts() ?? [] as $stmt) {
            [$findings, $denotation] = $walker->walk($stmt, false, $findings, $denotation);
        }

        return new FunctionAnalysis(
            $findings->findings(),
            $bindings->getParameters(),
            $bindings->getDeclaredGlobals(),
        );
    }
}
