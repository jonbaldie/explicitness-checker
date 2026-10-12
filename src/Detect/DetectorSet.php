<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Mode;
use JonBaldie\ExplicitnessChecker\Scope\Bindings;
use JonBaldie\ExplicitnessChecker\Scope\Denotation;

/**
 * Chooses the detectors for one analysis from the enabled modes.
 */
class DetectorSet
{
    /**
     * @return list<Detector>
     */
    public function select(Bindings $bindings, Mode $mode, Denotation $denotation, ?ReportableFunctionCall $reportable = null, bool $constructor = false): array
    {
        $detectors = [
            new VariableDetector($bindings, $denotation),
            new GlobalsArrayDetector(),
            new StaticCallDetector(),
            new FunctionCallDetector($reportable),
            new ArgumentMutationDetector($denotation),
            new StaticPropertyDetector(),
        ];
        if ($mode->isStrict()) {
            $detectors[] = new BuiltinCatalogueDetector();
        }
        if ($mode->isProps() || !$constructor) {
            $detectors[] = new ObjectPropertyDetector($mode->isProps());
        }

        return $detectors;
    }
}
