<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

use JonBaldie\ExplicitnessChecker\Analyser;
use JonBaldie\ExplicitnessChecker\Mode;
use JonBaldie\ExplicitnessChecker\Scope\CheckedFunctionLike;
use JonBaldie\ExplicitnessChecker\Scope\FunctionLikeFinder;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;

/**
 * Parses one file and checks every function-like in it, in source order.
 *
 * Detection lives in the shared Analyser; this turns its results into
 * Violations and verbose messages. Names are resolved against the namespace
 * and `use` imports as PHPStan resolves them, so the CLI and the PHPStan rule
 * report the same names. A file that fails to parse is reported on standard
 * error and skipped.
 */
class FileChecker
{
    public function __construct(
        protected Parser $parser,
        protected Analyser $analyser,
        protected FunctionLikeFinder $finder,
        protected Console $console,
        protected Mode $mode,
    ) {
    }

    /**
     * @return list<Violation>
     */
    public function check(string $file): array
    {
        $this->console->verbose("Parsing file: {$file}");
        $code = file_get_contents($file);
        if ($code === false) {
            $this->console->verbose("Failed to read file: {$file}");

            return [];
        }

        try {
            $ast = $this->parser->parse($code);
        } catch (Error $error) {
            $this->console->error("Parse error in {$file}: " . $error->getMessage() . PHP_EOL);

            return [];
        }
        if ($ast === null) {
            $this->console->verbose("No AST produced for file: {$file}");

            return [];
        }

        $violations = [];
        foreach ($this->finder->find($this->resolveNames($ast)) as $functionLike) {
            $violation = $this->checkFunctionLike($functionLike, $file);
            if ($violation !== null) {
                $violations[] = $violation;
            }
        }

        return $violations;
    }

    /**
     * Resolves names with the options PHPStan's own NameResolver service uses.
     *
     * @param array<Node> $ast
     *
     * @return array<Node>
     */
    protected function resolveNames(array $ast): array
    {
        return (new NodeTraverser(new NameResolver(null, ['preserveOriginalNames' => true])))->traverse($ast);
    }

    protected function checkFunctionLike(CheckedFunctionLike $functionLike, string $file): ?Violation
    {
        $node = $functionLike->getNode();
        $analysis = $this->analyser->analyse($node, $this->mode);
        $name = $functionLike->getName();
        $inputs = $analysis->getImplicitInputs();
        $outputs = $analysis->getImplicitOutputs();

        $this->console->verbose("Analyzing function/method: {$name} (line {$node->getStartLine()})");
        $this->verboseList("  Declared globals in {$name}: ", ', ', $analysis->getDeclaredGlobals());
        $this->verboseList("  Parameters for {$name}: ", ', ', $analysis->getParameters());

        if ($inputs === [] && $outputs === []) {
            $this->console->verbose("  No implicit inputs/outputs detected for {$name}");

            return null;
        }
        $violation = new Violation($file, $node->getStartLine(), $name, $inputs, $outputs);
        $this->verboseList("  Implicit inputs for {$name}: ", '; ', $violation->getInputs());
        $this->verboseList("  Implicit outputs for {$name}: ", '; ', $violation->getOutputs());

        return $violation;
    }

    /**
     * @param array<string> $items
     */
    protected function verboseList(string $label, string $glue, array $items): void
    {
        if ($items !== []) {
            $this->console->verbose($label . implode($glue, $items));
        }
    }
}
