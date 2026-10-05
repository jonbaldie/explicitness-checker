<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\FindingCollector;
use JonBaldie\ExplicitnessChecker\GlobalsArray;
use JonBaldie\ExplicitnessChecker\Scope\Bindings;
use JonBaldie\ExplicitnessChecker\Scope\ReferenceAliases;
use JonBaldie\ExplicitnessChecker\VariableName;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Reads and writes of variables declared `global` or `static`, of variables a
 * closure captures by reference, and of superglobals, by each name's
 * winning binding. `$this` and parameters are never implicit.
 */
class VariableDetector implements Detector
{
    protected const SUPERGLOBALS = [
        '_GET' => true,
        '_POST' => true,
        '_REQUEST' => true,
        '_SERVER' => true,
        '_FILES' => true,
        '_COOKIE' => true,
        '_ENV' => true,
        '_SESSION' => true,
        GlobalsArray::NAME => true,
    ];

    /**
     * What each implicit binding is reported as.
     */
    protected const DESCRIPTIONS = [
        Bindings::GLOBAL => ['global variable $', Category::GLOBAL_VARIABLE],
        Bindings::STATIC => ['static variable $', Category::STATIC_VARIABLE],
        Bindings::CAPTURED_REFERENCE => ['captured reference $', Category::CAPTURED_REFERENCE],
    ];

    public function __construct(protected Bindings $bindings, protected ReferenceAliases $aliases)
    {
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

        $kind = $this->bindings->kindOf($name);
        if ($kind !== null && isset(self::DESCRIPTIONS[$kind])) {
            [$prefix, $category] = self::DESCRIPTIONS[$kind];
            $findings->access($isWrite, $prefix . $name, $category, $node, $name);

            return;
        }

        if ($kind === null && isset(self::SUPERGLOBALS[$name])) {
            $findings->access($isWrite, 'superglobal $' . $name, Category::SUPERGLOBAL, $node, $name);
        }
    }
}
