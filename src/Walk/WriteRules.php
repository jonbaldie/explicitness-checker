<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

/**
 * The rules for nodes that write to some of their children: assignments,
 * increments and decrements, foreach targets, catch variables, unset and
 * by-reference arguments to built-ins.
 */
class WriteRules extends RuleChain
{
    public function __construct()
    {
        $this->rules = [
            new ReferenceAssignmentRule(),
            new AssignmentRule(),
            new CompoundAssignmentRule(),
            new IncrementDecrementRule(),
            new ForeachRule(),
            new CatchRule(),
            new UnsetRule(),
            new ByReferenceCallRule(),
        ];
    }
}
