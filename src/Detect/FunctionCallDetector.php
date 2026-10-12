<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\FindingCollector;
use PhpParser\Node;

class FunctionCallDetector implements Detector
{
    protected ReportableFunctionCall $reportable;

    public function __construct(?ReportableFunctionCall $reportable = null)
    {
        $this->reportable = $reportable ?? new ReportableFunctionCall();
    }

    public function detect(Node $node, bool $isWrite, FindingCollector $findings): FindingCollector
    {
        $name = $this->reportable->spelling($node);
        if ($name === null) {
            return $findings;
        }

        $subject = 'function ' . $name . '()';
        $findings = $findings->input('may read via ' . $subject, Category::FUNCTION_CALL, $node);
        $findings = $findings->output('may write via ' . $subject, Category::FUNCTION_CALL, $node);
        return $findings;
    }
}
