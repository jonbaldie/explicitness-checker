<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use JonBaldie\ExplicitnessChecker\Scope\CheckedFunctionLike;
use JonBaldie\ExplicitnessChecker\Scope\FunctionLikeFinder;

/**
 * The source-level check: parses PHP source, resolves names, and reports the
 * implicit inputs and outputs of every function-like, in source order.
 *
 * Name resolution is part of the pipeline, not the caller's job: without it,
 * `use function Other\time` imports are mistaken for built-ins, and
 * namespaced static properties are named with the source spelling rather than
 * the name PHP itself uses. Frontends that already hold resolved nodes, like
 * the PHPStan rule, may combine FunctionLikeFinder and Analyser directly
 * instead; see their contract for what that requires.
 *
 * Throws Error when the source does not parse.
 */
class SourceChecker
{
    protected Parser $parser;

    public function __construct(?Parser $parser = null)
    {
        $this->parser = $parser ?? (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * @return list<FunctionResult>
     *
     * @throws Error
     */
    public function check(string $source, Mode $mode): array
    {
        $ast = $this->parser->parse($source);
        if ($ast === null) {
            // Only a parser with a non-throwing error handler returns null;
            // the one this class builds throws instead.
            return [];
        }

        return $this->checkAst($ast, $mode);
    }

    /**
     * Checks an already-parsed AST, resolving names first. For callers who
     * parsed elsewhere and did not resolve names; prefer check() for raw
     * source.
     *
     * @param array<Node> $ast
     *
     * @return list<FunctionResult>
     */
    public function checkAst(array $ast, Mode $mode): array
    {
        $results = [];
        foreach ((new FunctionLikeFinder())->find($this->resolveNames($ast)) as $functionLike) {
            $results[] = $this->result($functionLike, $mode);
        }

        return $results;
    }

    /**
     * Resolves names to fully qualified ones, as PHPStan's parser does.
     *
     * @param array<Node> $ast
     *
     * @return array<Node>
     */
    protected function resolveNames(array $ast): array
    {
        return (new NodeTraverser(new NameResolver()))->traverse($ast);
    }

    protected function result(CheckedFunctionLike $functionLike, Mode $mode): FunctionResult
    {
        $analysis = (new Analyser())->analyse($functionLike->getNode(), $mode);

        return new FunctionResult(
            $functionLike->getName(),
            $functionLike->getNode()->getStartLine(),
            $analysis,
        );
    }
}
