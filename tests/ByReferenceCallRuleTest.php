<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Tests\Support\SyntheticByReferenceParameters;
use JonBaldie\ExplicitnessChecker\Walk\ByReferenceCallRule;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

/**
 * #80: ByReferenceCallRule takes by-reference positions from its
 * ByReferenceParameters, so it can be driven by built-ins that exist only in
 * the test, independent of the running PHP.
 */
class ByReferenceCallRuleTest extends TestCase
{
    /**
     * A by-reference argument, positional or named, is read and then written;
     * the rest keep the call's own mode.
     */
    public function testMarksByReferenceArgumentsOfSyntheticBuiltins(): void
    {
        $rule = new ByReferenceCallRule(new SyntheticByReferenceParameters(['fill' => [[1], ['out']]]));
        $call = $this->call('fill($a, $b, $c, out: $d);');
        [$a, $b, $c, $d] = $call->args;

        self::assertSame(
            [[$call->name, false], [$a, false], [$b, false], [$b, true], [$c, false], [$d, false], [$d, true]],
            $rule->children($call, false),
        );
    }

    /**
     * A function the lookup doesn't know as a built-in isn't claimed, even
     * where the running PHP defines it by reference.
     */
    public function testLeavesFunctionsTheLookupDoesNotKnowUnclaimed(): void
    {
        $rule = new ByReferenceCallRule(new SyntheticByReferenceParameters(['fill' => [[0], []]]));

        self::assertNull($rule->children($this->call('sort($a);'), false));
    }

    /**
     * Without a lookup, the rule reflects on the running PHP.
     */
    public function testDefaultsToTheRunningPhp(): void
    {
        $call = $this->call('sort($a);');
        [$a] = $call->args;

        self::assertSame([[$call->name, true], [$a, false], [$a, true]], (new ByReferenceCallRule())->children($call, true));
    }

    protected function call(string $code): Expr\FuncCall
    {
        $statements = (new ParserFactory())->createForNewestSupportedVersion()->parse('<?php ' . $code);
        $statement = $statements[0] ?? null;
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        self::assertInstanceOf(Expr\FuncCall::class, $statement->expr);

        return $statement->expr;
    }
}
