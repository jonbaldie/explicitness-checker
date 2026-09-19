<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

/**
 * Chooses the detectors for one analysis from the enabled modes.
 */
class DetectorSet
{
    /**
     * @param list<string> $parameters
     * @param list<string> $declaredGlobals
     * @param bool         $strict          also report output functions, file, time, random,
     *                                      environment, header, error-log and session access
     * @param bool         $props           also report `$this->prop` and `Class::$prop` access
     *
     * @return list<Detector>
     */
    public function select(array $parameters, array $declaredGlobals, bool $strict, bool $props): array
    {
        $detectors = [
            new VariableDetector($parameters, $declaredGlobals),
            new GlobalsArrayDetector(),
        ];
        if ($strict) {
            $detectors[] = new LanguageConstructDetector();
            $detectors[] = new FunctionCallDetector();
        }
        if ($props) {
            $detectors[] = new ObjectPropertyDetector();
            $detectors[] = new StaticPropertyDetector();
        }

        return $detectors;
    }
}
