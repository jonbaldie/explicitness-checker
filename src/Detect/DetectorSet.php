<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Mode;
use JonBaldie\ExplicitnessChecker\Walk\ReferenceAliases;

/**
 * Chooses the detectors for one analysis from the enabled modes.
 */
class DetectorSet
{
    /**
     * @param list<string> $parameters
     * @param list<string> $byReferenceParameters
     * @param list<string> $declaredGlobals
     * @param list<string> $staticVariables
     * @param list<string> $capturedReferences
     *
     * @return list<Detector>
     */
    public function select(
        array $parameters,
        array $byReferenceParameters,
        array $declaredGlobals,
        array $staticVariables,
        array $capturedReferences,
        Mode $mode,
        ReferenceAliases $aliases,
    ): array
    {
        $detectors = [
            new VariableDetector($parameters, $declaredGlobals, $staticVariables, $capturedReferences, $aliases),
            new GlobalsArrayDetector(),
            new StaticCallDetector(),
            new ArgumentMutationDetector($parameters, $byReferenceParameters),
            new StaticPropertyDetector(),
        ];
        if ($mode->isStrict()) {
            $detectors[] = new LanguageConstructDetector();
            $detectors[] = new ExitDetector();
            $detectors[] = new FunctionCallDetector();
            $detectors[] = new NewExpressionDetector();
        }
        if ($mode->isProps()) {
            $detectors[] = new ObjectPropertyDetector();
        }

        return $detectors;
    }
}
