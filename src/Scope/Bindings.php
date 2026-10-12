<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Scope;

/**
 * What each literal variable name refers to inside one function-like body.
 *
 * Precedence follows PHP, per body rather than per statement: a `global`
 * declaration rebinds a name whatever else it is, then a `static`
 * declaration, then a by-reference parameter, a plain parameter and a
 * by-reference closure capture. A name with no binding is local, or a
 * superglobal.
 */
class Bindings
{
    public const GLOBAL = 'global';
    public const STATIC = 'static';
    public const BY_REFERENCE_PARAMETER = 'byReferenceParameter';
    public const PARAMETER = 'parameter';
    public const CAPTURED_REFERENCE = 'capturedReference';

    /** @var array<string, self::*> */
    protected array $kinds = [];

    /**
     * @param list<string> $parameters
     * @param list<string> $byReferenceParameters
     * @param list<string> $declaredGlobals
     * @param list<string> $staticVariables
     * @param list<string> $capturedReferences
     */
    public function __construct(
        protected array $parameters,
        array $byReferenceParameters,
        protected array $declaredGlobals,
        protected bool $hasDynamicGlobal,
        array $staticVariables,
        array $capturedReferences,
    ) {
        // Lowest precedence first, so each later kind overrides the earlier.
        $this->kinds = self::bound($this->kinds, $capturedReferences, self::CAPTURED_REFERENCE);
        $this->kinds = self::bound($this->kinds, $parameters, self::PARAMETER);
        $this->kinds = self::bound($this->kinds, $byReferenceParameters, self::BY_REFERENCE_PARAMETER);
        $this->kinds = self::bound($this->kinds, $staticVariables, self::STATIC);
        $this->kinds = self::bound($this->kinds, $declaredGlobals, self::GLOBAL);
    }

    /**
     * The winning binding of a name without "$", or null if it has none.
     *
     * @return self::*|null
     */
    public function kindOf(string $name): ?string
    {
        return $this->kinds[$name] ?? null;
    }

    public function isParameter(string $name): bool
    {
        $kind = $this->kindOf($name);

        return $kind === self::PARAMETER || $kind === self::BY_REFERENCE_PARAMETER;
    }

    /**
     * Whether the body has a `global` declaration with a variable-variable
     * name, so any variable-variable in it may be a global.
     */
    public function hasDynamicGlobal(): bool
    {
        return $this->hasDynamicGlobal;
    }

    /**
     * Every declared parameter, whatever its name is rebound to.
     *
     * @return list<string>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Every name declared `global`, in discovery order.
     *
     * @return list<string>
     */
    public function getDeclaredGlobals(): array
    {
        return $this->declaredGlobals;
    }

    /**
     * @param array<string, self::*> $kinds
     * @param list<string> $names
     * @param self::* $kind
     * @return array<string, self::*>
     */
    protected static function bound(array $kinds, array $names, string $kind): array
    {
        foreach ($names as $name) {
            $kinds[$name] = $kind;
        }
        return $kinds;
    }
}
