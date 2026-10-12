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
use JonBaldie\ExplicitnessChecker\Walk\ReflectedByReferenceParameters;

/**
 * The source-level check: parses PHP source, resolves names, and reports the
 * implicit inputs and outputs of every function-like, in source order.
 *
 * Name resolution is part of the pipeline, not the caller's job: without it,
 * `use function Other\time` imports are mistaken for built-ins, and
 * namespaced static properties are named with the source spelling rather than
 * the name PHP itself uses. Frontends that already hold resolved nodes, like
 * the PHPStan rule, may combine FunctionLikeFinder and Analyser directly
 * instead; see FunctionLikeFinder's contract for what that requires.
 *
 * Throws Error when the source does not parse.
 */class SourceChecker
{
    protected Parser $parser;

    protected ReflectedByReferenceParameters $parameters;

    public function __construct(?ReflectedByReferenceParameters $parameters = null)
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->parameters = $parameters ?? new ReflectedByReferenceParameters();
    }

    /**
     * @return list<FunctionResult>
     *
     * @throws Error
     */
    public function check(string $source, Mode $mode): array
    {
        return $this->checkWithCache($source, $mode)[0];
    }

    public function withParameters(ReflectedByReferenceParameters $parameters): self
    {
        $next = clone $this;
        $next->parameters = $parameters;

        return $next;
    }

    /** @return array{list<FunctionResult>, ReflectedByReferenceParameters} */
    public function checkWithCache(string $source, Mode $mode): array
    {
        return $this->checkAstWithCache($this->parse($source), $mode);
    }

    /** @return array<Node> resolved AST
     *  @throws Error
     */
    public function parse(string $source): array
    {
        return $this->resolveNames((array) $this->parser->parse($source));
    }

    /** @param array<Node> $ast resolved nodes
     *  @return list<FunctionResult>
     */
    public function checkAst(array $ast, Mode $mode): array
    {
        return $this->checkAstWithCache($ast, $mode)[0];
    }

    /** @param array<Node> $ast resolved nodes
     *  @return array{list<FunctionResult>, ReflectedByReferenceParameters}
     */
    public function checkAstWithCache(array $ast, Mode $mode): array
    {
        $parameters = $this->parameters->forNodes($ast);
        $analyser = new Analyser($parameters);
        $results = [];
        foreach ((new FunctionLikeFinder())->find($ast) as $functionLike) {
            $results[] = $this->result($functionLike, $mode, $analyser);
        }

        return [$results, $parameters];
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

    protected function result(CheckedFunctionLike $functionLike, Mode $mode, Analyser $analyser): FunctionResult
    {
        $analysis = $analyser->analyse($functionLike->getNode(), $mode);

        return new FunctionResult(
            $functionLike->getName(),
            $functionLike->getNode()->getStartLine(),
            $analysis,
        );
    }
}
