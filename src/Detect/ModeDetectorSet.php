<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Mode;

/**
 * Selects the detectors that are enabled only by optional analysis modes.
 */
class ModeDetectorSet
{
    /**
     * @return list<Detector>
     */
    public function select(Mode $mode): array
    {
        $detectors = [];
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
