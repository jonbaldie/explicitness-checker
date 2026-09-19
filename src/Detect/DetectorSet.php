<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Mode;

/**
 * Chooses the detectors for one analysis from the enabled modes.
 */
class DetectorSet
{
    /**
     * @param list<string> $parameters
     * @param list<string> $declaredGlobals
     *
     * @return list<Detector>
     */
    public function select(array $parameters, array $declaredGlobals, Mode $mode): array
    {
        $detectors = [
            new VariableDetector($parameters, $declaredGlobals),
            new GlobalsArrayDetector(),
        ];
        if ($mode->isStrict()) {
            $detectors[] = new LanguageConstructDetector();
            $detectors[] = new FunctionCallDetector();
        }
        if ($mode->isProps()) {
            $detectors[] = new ObjectPropertyDetector();
            $detectors[] = new StaticPropertyDetector();
        }

        return $detectors;
    }
}
