<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use PhpParser\Node;
use PhpParser\Node\Stmt;

/**
 * `static $a, $b = expr;`: a declaration is neither a read nor a write, so the
 * declared variables are not walked; each initial value is read.
 */
class StaticDeclarationRule implements ChildAccessRule
{
    public function children(Node $node, bool $isWrite): ?array
    {
        if (!$node instanceof Stmt\Static_) {
            return null;
        }

        $children = [];
        foreach ($node->vars as $staticVar) {
            if ($staticVar->default !== null) {
                $children[] = [$staticVar->default, false];
            }
        }

        return $children;
    }
}
