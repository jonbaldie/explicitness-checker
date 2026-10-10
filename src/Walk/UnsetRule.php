<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use JonBaldie\ExplicitnessChecker\Superglobals;
use JonBaldie\ExplicitnessChecker\VariableName;
use PhpParser\Node;
use PhpParser\Node\Stmt;

/**
 * `unset($a, $b)` writes each argument, except a bare variable that is not a
 * superglobal. Unsetting that symbol only drops the local binding: a
 * by-reference parameter, a `global` import, a static variable, a captured
 * reference, or a local alias of `$GLOBALS[...]` is left unchanged.
 */
class UnsetRule implements ChildAccessRule
{
    public function children(Node $node, bool $isWrite): ?array
    {
        if (!$node instanceof Stmt\Unset_) {
            return null;
        }

        $children = [];
        foreach ($node->vars as $var) {
            $name = VariableName::of($var);
            if ($name !== null && !Superglobals::includes($name)) {
                continue;
            }
            $children[] = [$var, true];
        }

        return $children;
    }
}
