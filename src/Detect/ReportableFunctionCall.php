<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Walk\ByReferenceParameters;
use JonBaldie\ExplicitnessChecker\Walk\ReflectedByReferenceParameters;
use PhpParser\Node;
use PhpParser\Node\Expr;

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
