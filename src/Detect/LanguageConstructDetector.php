<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\FindingCollector;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Strict mode: `echo` and `print` write to standard output.
 */
class LanguageConstructDetector implements Detector
{
    protected const CONSTRUCTS = [
        Stmt\Echo_::class => 'echo',
        Expr\Print_::class => 'print',
    ];

    public function detect(Node $node, bool $isWrite, FindingCollector $findings): void
    {
        foreach (self::CONSTRUCTS as $class => $construct) {
            if ($node instanceof $class) {
                $findings->output(
                    'writes to standard output (' . $construct . ')',
                    Category::STANDARD_OUTPUT,
                    $node,
                );

                return;
            }
        }
    }
}
