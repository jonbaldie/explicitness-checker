<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use PhpParser\Node;

/**
 * The ordered list of ChildAccessRules. The first rule that applies to a node
 * decides which of its children are walked and in which access mode.
 */
class AccessRules extends RuleChain
{
    public function __construct()
    {
        $this->rules = [
            new WriteRules(),
            new LeafRule(),
            new ArrayIndexRule(),
            new PropertyFetchRule(),
            new StaticDeclarationRule(),
            new SubNodesRule(),
        ];
    }

    /**
     * @return list<array{Node, bool}>
     */
    public function childrenOf(Node $node, bool $isWrite): array
    {
        return $this->children($node, $isWrite) ?? [];
    }
}
