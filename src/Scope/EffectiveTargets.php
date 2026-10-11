<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Scope;

use JonBaldie\ExplicitnessChecker\VariableName;
use PhpParser\Node;

/**
 * Resolves the current target of a variable during one function-like walk.
 * A known GLOBALS alias supersedes the per-body declaration until the
 * existing ReferenceAliases lifecycle forgets it.
 */
class EffectiveTargets
{
    public const GLOBALS_ALIAS = 'globalsAlias';

    public function __construct(protected Bindings $bindings, protected ReferenceAliases $aliases)
    {
    }

    /**
     * @return array{kind: Bindings::*|self::GLOBALS_ALIAS|null, subject: string|null}
     */
    public function targetOf(Node $node): array
    {
        $subject = $this->aliases->targetOf($node);
        if ($subject !== null) {
            return ['kind' => self::GLOBALS_ALIAS, 'subject' => $subject];
        }

        $name = VariableName::of($node);

        return ['kind' => $name === null ? null : $this->bindings->kindOf($name), 'subject' => null];
    }
}
