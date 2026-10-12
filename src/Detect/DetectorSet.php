<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Mode;
use JonBaldie\ExplicitnessChecker\Scope\Bindings;
use JonBaldie\ExplicitnessChecker\Scope\EffectiveTargets;
use JonBaldie\ExplicitnessChecker\Scope\ReferenceAliases;

/**
 * Chooses the detectors for one analysis from the enabled modes.
 */
class DetectorSet
{
    /**
     * @return list<Detector>
     */
    public function select(Bindings $bindings, Mode $mode, ReferenceAliases $aliases): array
    {
        $targets = new EffectiveTargets($bindings, $aliases);
        $detectors = [
            new VariableDetector($bindings, $targets),
            new GlobalsArrayDetector(),
            new StaticCallDetector(),
            new FunctionCallDetector(),
            new ArgumentMutationDetector($targets),
            new StaticPropertyDetector(),
        ];
        if ($mode->isStrict()) {
            $detectors[] = new BuiltinCatalogueDetector();
        }
        if ($mode->isProps()) {
            $detectors[] = new ObjectPropertyDetector();
        }

        return $detectors;
    }
}
