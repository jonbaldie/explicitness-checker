<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\FindingCollector;
use PhpParser\Node;

/**
 * Inspects one node (not its children) and records any implicit input or
 * output it represents.
 */
interface Detector
{
    /**
     * @param bool $isWrite whether the walk reached this node as a write target
     */
    public function detect(Node $node, bool $isWrite, FindingCollector $findings): void;
}
