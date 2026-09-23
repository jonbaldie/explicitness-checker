<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use JonBaldie\ExplicitnessChecker\VariableName;
use PhpParser\Node;
use PhpParser\Node\Stmt;

/**
 * Collects the variable names a body declares with `static`, the same way
 * GlobalDeclarations collects `global` names.
 */
class StaticDeclarations extends GlobalDeclarations
{
    protected function declaredNames(Node $node): ?array
    {
        if (!$node instanceof Stmt\Static_) {
            return null;
        }

        $names = [];
        foreach ($node->vars as $staticVar) {
            $name = VariableName::of($staticVar->var);
            if ($name !== null) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
