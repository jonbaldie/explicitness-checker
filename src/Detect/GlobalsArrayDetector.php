<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\FindingCollector;
use JonBaldie\ExplicitnessChecker\Scope\Denotation;
use JonBaldie\ExplicitnessChecker\GlobalsArray;
use PhpParser\Node;

/**
 * Reads and writes of `$GLOBALS[...]`, by the subject GlobalsArray gives them.
 */
class GlobalsArrayDetector implements Detector
{
    public function detect(Node $node, bool $isWrite, FindingCollector $findings, Denotation $denotation): FindingCollector
    {
        $subject = GlobalsArray::subjectOf($node);
        if ($subject === null) {
            return $findings;
        }

        $findings = $findings->access($isWrite, $subject, Category::GLOBALS_ARRAY, $node);
        return $findings;
    }
}
