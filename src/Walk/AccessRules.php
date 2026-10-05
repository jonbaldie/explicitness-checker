<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use PhpParser\Node;

/**
 * The ChildAccessRules of the walk. A node claimed by a rule walks the
 * children that rule returns; a node no rule claims walks every child, in its
 * own mode.
 *
 * The rules are asked in order and the first claim wins, but they claim
 * disjoint kinds of node, so the order decides nothing. Keep it that way:
 * a new rule claims only nodes no other rule claims, or an existing rule takes
 * on the new case. AccessRulesTest asks every rule about every node of the
 * fixtures and src, and fails if two of them claim one; a fixture is the place
 * to show a node kind it would otherwise miss. The catch-all is not
 * in the list for the same reason: it claims every node.
 */
class AccessRules extends RuleChain
{
    protected SubNodesRule $unclaimed;

    public function __construct()
    {
        $this->rules = [
            new WriteRules(new ReflectedByReferenceParameters()),
            new LeafRule(),
            new ArrayIndexRule(),
            new ArrayItemRule(),
            new PropertyFetchRule(),
            new StaticDeclarationRule(),
        ];
        $this->unclaimed = new SubNodesRule();
    }

    /**
     * @return list<array{Node, bool}>
     */
    public function childrenOf(Node $node, bool $isWrite): array
    {
        return $this->children($node, $isWrite) ?? $this->unclaimed->children($node, $isWrite);
    }
}
