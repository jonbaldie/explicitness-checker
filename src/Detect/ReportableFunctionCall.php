<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Walk\ByReferenceParameters;
use JonBaldie\ExplicitnessChecker\Walk\ReflectedByReferenceParameters;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * The spelling of a direct, named, zero-argument call that default mode
 * should report, or null when this rule stays quiet.
 *
 * Quiet covers every other node shape, a callee that is not a Name, a
 * non-empty argument list (arguments, unpack, and first-class `...`),
 * a name the strict catalogue would already report for an empty call,
 * and a name the running PHP reports as internal.
 *
 * A user-defined function loaded in this process is reported. An unknown
 * name is reported. The checker does not include the analysed file.
 *
 * Pass Name::toString() through unchanged. The full spelling is the
 * catalogue key and the reflection key. 'App\time' is not the built-in
 * 'time'. Case folding stays inside the catalogue and inside isBuiltin.
 *
 * Detectors call this method. They do not combine wouldReportEmptyCall
 * with ByReferenceParameters::isBuiltin themselves.
 */
final class ReportableFunctionCall
{
    protected ByReferenceParameters $loaded;

    protected BuiltinCatalogueDetector $catalogue;

    public function __construct(?ByReferenceParameters $loaded = null)
    {
        $this->loaded = $loaded ?? new ReflectedByReferenceParameters();
        $this->catalogue = new BuiltinCatalogueDetector();
    }

    public function spelling(Node $node): ?string
    {
        if (!$node instanceof Expr\FuncCall) {
            return null;
        }
        if (!$node->name instanceof Node\Name || $node->args !== []) {
            return null;
        }

        $spelling = $node->name->toString();
        if ($this->catalogue->wouldReportEmptyCall($spelling)) {
            return null;
        }
        if ($this->loaded->isBuiltin($spelling)) {
            return null;
        }

        return $spelling;
    }
}
