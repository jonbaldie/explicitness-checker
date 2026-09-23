<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use PhpParser\Node;

/**
 * An ordered list of ChildAccessRules: the first rule that applies to a node
 * decides.
 */
abstract class RuleChain implements ChildAccessRule
{
    /** @var list<ChildAccessRule> */
    protected array $rules;

    public function children(Node $node, bool $isWrite): ?array
    {
        foreach ($this->rules as $rule) {
            $children = $rule->children($node, $isWrite);
            if ($children !== null) {
                return $children;
            }
        }

        return null;
    }
}
