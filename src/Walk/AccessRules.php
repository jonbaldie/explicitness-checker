<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use PhpParser\Node;

/**
 * The ordered list of ChildAccessRules. The first rule that applies to a node
 * decides which of its children are walked and in which access mode.
 */
class AccessRules
{
    /** @var list<ChildAccessRule> */
    protected array $rules;

    public function __construct()
    {
        $this->rules = [
            new AssignmentRule(),
            new CompoundAssignmentRule(),
            new IncrementDecrementRule(),
            new LeafRule(),
            new ArrayIndexRule(),
            new ForeachRule(),
            new CatchRule(),
            new UnsetRule(),
            new SubNodesRule(),
        ];
    }

    /**
     * @return list<array{Node, bool}>
     */
    public function childrenOf(Node $node, bool $isWrite): array
    {
        foreach ($this->rules as $rule) {
            $children = $rule->children($node, $isWrite);
            if ($children !== null) {
                return $children;
            }
        }

        return [];
    }
}
