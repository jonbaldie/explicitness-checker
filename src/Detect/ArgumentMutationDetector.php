<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\FindingCollector;
use JonBaldie\ExplicitnessChecker\Scope\Bindings;
use JonBaldie\ExplicitnessChecker\Scope\EffectiveTargets;
use JonBaldie\ExplicitnessChecker\VariableName;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Writes that change the caller's data through an argument: any write to a
 * by-reference parameter, and any write to a property reached from a
 * parameter, which is a handle on an object the caller shares. Both are
 * described by the root parameter. A parameter rebound by `global` or
 * `static`, or a known GLOBALS reference alias, is no longer the caller's
 * argument. Reads of arguments are never implicit.
 */
class ArgumentMutationDetector implements Detector
{
    public function __construct(protected EffectiveTargets $targets)
    {
    }

    public function detect(Node $node, bool $isWrite, FindingCollector $findings): void
    {
        if (!$isWrite) {
            return;
        }

        $name = VariableName::of($node);
        if ($name !== null && $this->targets->targetOf($node)['kind'] === Bindings::BY_REFERENCE_PARAMETER) {
            $findings->output('wrote to argument $' . $name, Category::ARGUMENT_MUTATION, $node, $name);

            return;
        }

        if ($node instanceof Expr\PropertyFetch) {
            $rootNode = $this->rootOf($node);
            $root = VariableName::of($rootNode);
            $kind = $this->targets->targetOf($rootNode)['kind'];
            if ($root !== null && ($kind === Bindings::PARAMETER || $kind === Bindings::BY_REFERENCE_PARAMETER)) {
                $findings->output('wrote to argument $' . $root, Category::ARGUMENT_MUTATION, $node, $root);
            }
        }
    }

    /**
     * The variable at the end of a chain of property and element fetches.
     */
    protected function rootOf(Expr $node): Expr
    {
        while ($node instanceof Expr\PropertyFetch || $node instanceof Expr\ArrayDimFetch) {
            $node = $node->var;
        }

        return $node;
    }
}
