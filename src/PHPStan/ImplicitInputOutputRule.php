<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\PHPStan;

use JonBaldie\ExplicitnessChecker\Analyser;
use JonBaldie\ExplicitnessChecker\Finding;
use JonBaldie\ExplicitnessChecker\Mode;
use JonBaldie\ExplicitnessChecker\Scope\CheckedFunctionLike;
use JonBaldie\ExplicitnessChecker\Scope\FunctionLikeFinder;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Reports each distinct implicit input and output of every function-like in a
 * file, on the line where it first occurs.
 *
 * Runs once per file and picks function-likes and their names with the CLI's
 * FunctionLikeFinder, so both tools check the same code under the same names.
 * PHPStan's scope isn't used for naming: it names anonymous classes after the
 * file path, and analyses trait methods once per using class (or not at all).
 * PHPStan's parser resolves names itself, which satisfies
 * FunctionLikeFinder's resolved-AST contract; SourceChecker's own resolution
 * stays out of this hot path.
 *
 * @implements Rule<FileNode>
 */
class ImplicitInputOutputRule implements Rule
{
    public const IDENTIFIER_PREFIX = 'explicitness.';

    /**
     * @param Mode $mode from the `explicitness.strict` and `explicitness.props` parameters
     */
    public function __construct(
        protected Analyser $analyser,
        protected FunctionLikeFinder $finder,
        protected Mode $mode,
    ) {
    }

    public function getNodeType(): string
    {
        return FileNode::class;
    }

    /**
     * $scope is required by the Rule interface but unused: function-likes and
     * their names come from FunctionLikeFinder, as in the CLI.
     *
     * @param FileNode $node
     *
     * @return list<IdentifierRuleError>
     *
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];
        foreach ($this->finder->find($node->getNodes()) as $functionLike) {
            foreach ($this->findingsOf($functionLike) as $finding) {
                $errors[] = $this->error($functionLike->getName(), $finding);
            }
        }

        return $errors;
    }

    /**
     * Inputs then outputs, each in order of first occurrence.
     *
     * @return list<Finding>
     */
    protected function findingsOf(CheckedFunctionLike $functionLike): array
    {
        $analysis = $this->analyser->analyse($functionLike->getNode(), $this->mode);

        return array_merge($analysis->getImplicitInputs(), $analysis->getImplicitOutputs());
    }

    protected function error(string $functionName, Finding $finding): IdentifierRuleError
    {
        return RuleErrorBuilder::message($functionName . ' ' . $finding->getDescription() . '.')
            ->identifier(self::IDENTIFIER_PREFIX . $finding->getCategory())
            ->line($finding->getLine())
            ->build();
    }
}
