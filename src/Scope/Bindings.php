<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Scope;

/**
 * The winning binding for each variable name in one function-like.
 *
 * Precedence follows PHP's name binding: globals, statics, by-reference
 * parameters, other parameters, by-reference captures, then superglobals.
 */
class Bindings
{
    public const GLOBAL = 'global';
    public const STATIC = 'static';
    public const BY_REFERENCE_PARAMETER = 'by-reference-parameter';
    public const PARAMETER = 'parameter';
    public const CAPTURED_REFERENCE = 'captured-reference';
    public const SUPERGLOBAL = 'superglobal';

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

    /** @var list<string> */
    protected array $parameters;

    /** @var list<string> */
    protected array $declaredGlobals;

    protected bool $hasDynamicGlobal;

    /** @var array<string, 'global'|'static'|'by-reference-parameter'|'parameter'|'captured-reference'|'superglobal'> */
    protected array $kinds = [];

    /**
     * @param list<string> $parameters
     * @param list<string> $byReferenceParameters
     * @param list<string> $declaredGlobals
     * @param list<string> $staticVariables
     * @param list<string> $capturedReferences
     */
    public function __construct(
        array $parameters,
        array $byReferenceParameters,
        array $declaredGlobals,
        bool $hasDynamicGlobal,
        array $staticVariables,
        array $capturedReferences,
    ) {
        $this->parameters = $parameters;
        $this->declaredGlobals = $declaredGlobals;
        $this->hasDynamicGlobal = $hasDynamicGlobal;

        // Later entries have higher precedence, so every detector gets the
        // same answer without applying its own ranking to the source lists.
        foreach ([
            self::SUPERGLOBAL => array_keys(self::SUPERGLOBALS),
            self::CAPTURED_REFERENCE => $capturedReferences,
            self::PARAMETER => $parameters,
            self::BY_REFERENCE_PARAMETER => $byReferenceParameters,
            self::STATIC => $staticVariables,
            self::GLOBAL => $declaredGlobals,
        ] as $kind => $names) {
            foreach ($names as $name) {
                $this->kinds[$name] = $kind;
            }
        }
    }

    /**
     * @return 'global'|'static'|'by-reference-parameter'|'parameter'|'captured-reference'|'superglobal'|null
     */
    public function kindOf(string $name): ?string
    {
        return $this->kinds[$name] ?? null;
    }

    public function hasDynamicGlobal(): bool
    {
        return $this->hasDynamicGlobal;
    }

    /**
     * Names of the function-like's parameters, without "$".
     *
     * @return list<string>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Names declared with `global` in the body, without "$", in discovery order.
     *
     * @return list<string>
     */
    public function getDeclaredGlobals(): array
    {
        return $this->declaredGlobals;
    }
}
