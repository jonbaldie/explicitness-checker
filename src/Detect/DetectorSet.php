<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Mode;
use JonBaldie\ExplicitnessChecker\Scope\Bindings;
use JonBaldie\ExplicitnessChecker\Walk\ReferenceAliases;

/**
 * Chooses the detectors for one analysis from the enabled modes.
 */
class DetectorSet
{
    /**
     * @return list<Detector>
     */
    public function select(
        Bindings $bindings,
        Mode $mode,
        ReferenceAliases $aliases,
    ): array
    {
        $detectors = [
            new VariableDetector($bindings, $aliases),
            new GlobalsArrayDetector(),
            new StaticCallDetector(),
            new ArgumentMutationDetector($bindings),
            new StaticPropertyDetector(),
        ];

        return array_merge($detectors, (new ModeDetectorSet())->select($mode));
    }
}
