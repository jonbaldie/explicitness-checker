<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\FindingCollector;
use JonBaldie\ExplicitnessChecker\Scope\Denotation;
use PhpParser\Node;

/**
 * Writes that change data the caller can observe through an argument.
 *
 * A fresh shallow copy of a by-value parameter is still that parameter, but a
 * write of the copy's own property is not a write the caller observes. A
 * nested object reached from the copy still is. A `global` or `static`
 * rebinding, and a known `$GLOBALS` alias, still win over the parameter.
 */
class ArgumentMutationDetector implements Detector
{
    public function __construct(protected Denotation $denotation)
    {
    }

    public function detect(Node $node, bool $isWrite, FindingCollector $findings): void
    {
        foreach ($this->denotation->mutatedParameters($node, $isWrite) as $parameter) {
            $findings->output('wrote to argument $' . $parameter, Category::ARGUMENT_MUTATION, $node, $parameter);
        }
    }
}
