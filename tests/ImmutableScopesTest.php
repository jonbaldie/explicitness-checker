<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Scope\JoinMap;
use JonBaldie\ExplicitnessChecker\Scope\ValueOrigins;
use PHPUnit\Framework\TestCase;
use JonBaldie\ExplicitnessChecker\Scope\FunctionLikeCollector;
use PhpParser\Node\Stmt;

class ImmutableScopesTest extends TestCase
{
    public function testBindingReturnsANewMapWithoutChangingThePreviousState(): void
    {
        $before = new JoinMap(['a' => ['a' => ValueOrigins::SHARED]]);
        $after = $before->bind('b', ['a' => ValueOrigins::SHARED]);

        self::assertInstanceOf(JoinMap::class, $after);
        self::assertSame([], $before->origins('b'));
        self::assertSame(['a' => ValueOrigins::SHARED], $after->origins('b'));
    }
    public function testFunctionDiscoveryReturnsANewCollector(): void
    {
        $before = new FunctionLikeCollector();
        $after = $before->enter(new Stmt\Function_('example', ['stmts' => []]));

        self::assertSame([], $before->getFound());
        self::assertCount(1, $after->getFound());
        self::assertSame('example', $after->getFound()[0]->getName());
    }

}
