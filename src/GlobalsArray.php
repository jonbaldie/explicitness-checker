<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;

/**
 * What a `$GLOBALS` access is. The walk, the reference-alias tracking and the
 * detectors all ask this one place, so they cannot disagree.
 *
 * Bare `$GLOBALS` is a superglobal named NAME. A `$GLOBALS[...]` fetch is
 * reported as a whole, by its subject, and its dimension is never walked.
 */
class GlobalsArray
{
    public const NAME = 'GLOBALS';

    /**
     * Whether the node is `$GLOBALS[...]` (with any dimension, or none).
     *
     * @phpstan-assert-if-true Expr\ArrayDimFetch $node
     */
    public static function isFetch(?Node $node): bool
    {
        return $node instanceof Expr\ArrayDimFetch
            && VariableName::of($node->var) === self::NAME;
    }

    /**
     * Returns the subject used in a finding for a `$GLOBALS[...]` fetch,
     * naming the key when it is a literal or a plain variable.
     */
    public static function subjectOf(Node $node): ?string
    {
        if (!self::isFetch($node)) {
            return null;
        }

        $key = self::keyToString($node->dim);

        return $key === null ? '$GLOBALS' : '$GLOBALS[' . $key . ']';
    }

    protected static function keyToString(?Expr $dim): ?string
    {
        if ($dim instanceof Scalar\String_) {
            return "'" . $dim->value . "'";
        }
        if ($dim instanceof Scalar\Int_) {
            return (string) $dim->value;
        }
        $name = VariableName::of($dim);

        return $name === null ? null : '$' . $name;
    }
}
