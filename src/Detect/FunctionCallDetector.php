<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\FindingCollector;
use PhpParser\Node;

class FunctionCallDetector implements Detector
{
    protected ReportableFunctionCall $reportable;

    public function __construct()
    {
        $this->reportable = new ReportableFunctionCall();
    }

    public function detect(Node $node, bool $isWrite, FindingCollector $findings): void
    {
        $name = $this->reportable->spelling($node);
        if ($name === null) {
            return;
        }

        $subject = 'function ' . $name . '()';
        $findings->input('may read via ' . $subject, Category::FUNCTION_CALL, $node);
        $findings->output('may write via ' . $subject, Category::FUNCTION_CALL, $node);
    }
}
