<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

/**
 * The detectors strict mode adds: language constructs, exits, catalogue
 * function calls and object creation.
 */
class StrictDetectors
{
    /**
     * @return list<Detector>
     */
    public function all(): array
    {
        return [
            new LanguageConstructDetector(),
            new ExitDetector(),
            new FunctionCallDetector(),
            new NewExpressionDetector(),
        ];
    }
}
