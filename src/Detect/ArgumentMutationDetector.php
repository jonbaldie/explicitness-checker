<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\FindingCollector;
use JonBaldie\ExplicitnessChecker\VariableName;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Writes that change the caller's data through an argument: any write to a
 * by-reference parameter, and any write to a property reached from a
 * parameter, which is a handle on an object the caller shares. Both are
 * described by the root parameter. Reads of arguments are never implicit.
 */
class ArgumentMutationDetector implements Detector
{
    /** @var array<string, true> */
    protected array $parameters;

    /** @var array<string, true> */
    protected array $byReferenceParameters;

    /**
     * @param list<string> $parameters
     * @param list<string> $byReferenceParameters
     */
    public function __construct(array $parameters, array $byReferenceParameters)
    {
        $this->parameters = array_fill_keys($parameters, true);
        $this->byReferenceParameters = array_fill_keys($byReferenceParameters, true);
    }

    public function detect(Node $node, bool $isWrite, FindingCollector $findings): void
    {
        if (!$isWrite) {
            return;
        }

        $name = VariableName::of($node);
        if ($name !== null && isset($this->byReferenceParameters[$name])) {
            $findings->output('wrote to argument $' . $name, Category::ARGUMENT_MUTATION, $node);

            return;
        }

        if ($node instanceof Expr\PropertyFetch) {
            $root = $this->rootOf($node);
            if ($root !== null && isset($this->parameters[$root])) {
                $findings->output('wrote to argument $' . $root, Category::ARGUMENT_MUTATION, $node);
            }
        }
    }

    /**
     * The variable at the end of a chain of property and element fetches.
     */
    protected function rootOf(Expr $node): ?string
    {
        while ($node instanceof Expr\PropertyFetch || $node instanceof Expr\ArrayDimFetch) {
            $node = $node->var;
        }

        return VariableName::of($node);
    }
}
