<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\FindingCollector;
use JonBaldie\ExplicitnessChecker\Scope\Bindings;
use JonBaldie\ExplicitnessChecker\VariableName;
use JonBaldie\ExplicitnessChecker\Walk\ReferenceAliases;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Reads and writes of variables declared `global` or `static`, of variables a
 * closure captures by reference, and of superglobals.
 * `$this` and parameters are never implicit.
 */
class VariableDetector implements Detector
{
    protected Bindings $bindings;

    protected ReferenceAliases $aliases;

    public function __construct(Bindings $bindings, ReferenceAliases $aliases)
    {
        $this->bindings = $bindings;
        $this->aliases = $aliases;
    }

    public function detect(Node $node, bool $isWrite, FindingCollector $findings): void
    {
        if ($this->detectAlias($node, $isWrite, $findings)) {
            return;
        }

        if ($this->detectDynamicGlobal($node, $isWrite, $findings)) {
            return;
        }

        $this->detectNamedVariable($node, $isWrite, $findings);
    }

    protected function detectAlias(Node $node, bool $isWrite, FindingCollector $findings): bool
    {
        $aliasTarget = $this->aliases->targetOf($node);
        if ($aliasTarget === null) {
            return false;
        }

        $findings->access($isWrite, $aliasTarget, Category::GLOBALS_ARRAY, $node);

        return true;
    }

    protected function detectDynamicGlobal(Node $node, bool $isWrite, FindingCollector $findings): bool
    {
        if (!$this->bindings->hasDynamicGlobal() || !$node instanceof Expr\Variable || is_string($node->name)) {
            return false;
        }

        $findings->access($isWrite, 'global variable $...', Category::GLOBAL_VARIABLE, $node);

        return true;
    }

    protected function detectNamedVariable(Node $node, bool $isWrite, FindingCollector $findings): void
    {
        $name = VariableName::of($node);
        if ($name === null) {
            return;
        }
        switch ($this->bindings->kindOf($name)) {
            case Bindings::GLOBAL:
                $findings->access($isWrite, 'global variable $' . $name, Category::GLOBAL_VARIABLE, $node);
                return;
            case Bindings::STATIC:
                $findings->access($isWrite, 'static variable $' . $name, Category::STATIC_VARIABLE, $node);
                return;
            case Bindings::CAPTURED_REFERENCE:
                $findings->access($isWrite, 'captured reference $' . $name, Category::CAPTURED_REFERENCE, $node);
                return;
            case Bindings::SUPERGLOBAL:
                $findings->access($isWrite, 'superglobal $' . $name, Category::SUPERGLOBAL, $node);
                return;
            case Bindings::BY_REFERENCE_PARAMETER:
            case Bindings::PARAMETER:
            case null:
                return;
        }
    }
}
