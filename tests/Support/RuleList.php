<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests\Support;

use JonBaldie\ExplicitnessChecker\Walk\ChildAccessRule;
use JonBaldie\ExplicitnessChecker\Walk\RuleChain;

/**
 * Reads the rules out of a RuleChain, so a test can ask each rule on its own
 * which nodes it claims (#57).
 */
class RuleList extends RuleChain
{
    /**
     * @return list<ChildAccessRule> the chain's leaf rules, nested chains flattened, in chain order
     */
    public static function flattened(RuleChain $chain): array
    {
        $rules = [];
        foreach ($chain->rules as $rule) {
            $rules = array_merge($rules, $rule instanceof RuleChain ? self::flattened($rule) : [$rule]);
        }

        return $rules;
    }
}
