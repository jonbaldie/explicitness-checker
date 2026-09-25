<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\FindingCollector;
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
    protected const SUPERGLOBALS = [
        '_GET' => true,
        '_POST' => true,
        '_REQUEST' => true,
        '_SERVER' => true,
        '_FILES' => true,
        '_COOKIE' => true,
        '_ENV' => true,
        '_SESSION' => true,
        'GLOBALS' => true,
    ];

    /** @var array<string, true> */
    protected array $parameters;

    /** @var array<string, true> */
    protected array $declaredGlobals;

    protected bool $hasDynamicGlobal;

    /** @var array<string, true> */
    protected array $staticVariables;

    /** @var array<string, true> */
    protected array $capturedReferences;

    protected ReferenceAliases $aliases;

    /**
     * @param list<string> $parameters
     * @param list<string> $declaredGlobals
     * @param bool $hasDynamicGlobal
     * @param list<string> $staticVariables
     * @param list<string> $capturedReferences
     */
    public function __construct(
        array $parameters,
        array $declaredGlobals,
        bool $hasDynamicGlobal,
        array $staticVariables,
        array $capturedReferences,
        ReferenceAliases $aliases,
    ) {
        $this->parameters = array_fill_keys($parameters, true);
        $this->declaredGlobals = array_fill_keys($declaredGlobals, true);
        $this->hasDynamicGlobal = $hasDynamicGlobal;
        $this->staticVariables = array_fill_keys($staticVariables, true);
        $this->capturedReferences = array_fill_keys($capturedReferences, true);
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
        if (!$this->hasDynamicGlobal || !$node instanceof Expr\Variable || is_string($node->name)) {
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
        if (isset($this->parameters[$name])) {
            return;
        }

        if ($this->recordGlobalVariable($name, $node, $isWrite, $findings)) {
            return;
        }

        if ($this->recordStaticVariable($name, $node, $isWrite, $findings)) {
            return;
        }

        if ($this->recordCapturedReference($name, $node, $isWrite, $findings)) {
            return;
        }

        $this->recordSuperglobal($name, $node, $isWrite, $findings);
    }

    protected function recordGlobalVariable(
        string $name,
        Node $node,
        bool $isWrite,
        FindingCollector $findings,
    ): bool {
        if (!isset($this->declaredGlobals[$name])) {
            return false;
        }

        $findings->access($isWrite, 'global variable $' . $name, Category::GLOBAL_VARIABLE, $node);

        return true;
    }

    protected function recordStaticVariable(
        string $name,
        Node $node,
        bool $isWrite,
        FindingCollector $findings,
    ): bool {
        if (!isset($this->staticVariables[$name])) {
            return false;
        }

        $findings->access($isWrite, 'static variable $' . $name, Category::STATIC_VARIABLE, $node);

        return true;
    }

    protected function recordCapturedReference(
        string $name,
        Node $node,
        bool $isWrite,
        FindingCollector $findings,
    ): bool {
        if (!isset($this->capturedReferences[$name])) {
            return false;
        }

        $findings->access($isWrite, 'captured reference $' . $name, Category::CAPTURED_REFERENCE, $node);

        return true;
    }

    protected function recordSuperglobal(string $name, Node $node, bool $isWrite, FindingCollector $findings): void
    {
        if (!isset(self::SUPERGLOBALS[$name])) {
            return;
        }

        $findings->access($isWrite, 'superglobal $' . $name, Category::SUPERGLOBAL, $node);
    }
}
