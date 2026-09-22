<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\FindingCollector;
use JonBaldie\ExplicitnessChecker\VariableName;
use JonBaldie\ExplicitnessChecker\Walk\ReferenceAliases;
use PhpParser\Node;

/**
 * Reads and writes of variables declared `global`, and of superglobals.
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

    protected ReferenceAliases $aliases;

    /**
     * @param list<string> $parameters
     * @param list<string> $declaredGlobals
     */
    public function __construct(array $parameters, array $declaredGlobals, ReferenceAliases $aliases)
    {
        $this->parameters = array_fill_keys($parameters, true);
        $this->declaredGlobals = array_fill_keys($declaredGlobals, true);
        $this->aliases = $aliases;
    }

    public function detect(Node $node, bool $isWrite, FindingCollector $findings): void
    {
        $aliasTarget = $this->aliases->targetOf($node);
        if ($aliasTarget !== null) {
            $findings->access($isWrite, $aliasTarget, Category::GLOBALS_ARRAY, $node);

            return;
        }

        $name = VariableName::of($node);
        if ($name === null) {
            return;
        }
        if (isset($this->parameters[$name])) {
            return;
        }

        if (isset($this->declaredGlobals[$name])) {
            $findings->access($isWrite, 'global variable $' . $name, Category::GLOBAL_VARIABLE, $node);

            return;
        }

        if (isset(self::SUPERGLOBALS[$name])) {
            $findings->access($isWrite, 'superglobal $' . $name, Category::SUPERGLOBAL, $node);
        }
    }
}
