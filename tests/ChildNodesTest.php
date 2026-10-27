<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\ChildNodes;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PHPUnit\Framework\TestCase;

class ChildNodesTest extends TestCase
{
    public function testDirectChildrenKeepSubNodeOrderAndIdentity(): void
    {
        $condition = new Expr\Variable('condition');
        $statement = new Stmt\Return_(new Scalar\Int_(1));
        $elseif = new Stmt\ElseIf_(new Expr\Variable('alternative'));
        $else = new Stmt\Else_();
        $node = new Stmt\If_($condition, [
            'stmts' => [$statement],
            'elseifs' => [$elseif],
            'else' => $else,
        ], ['ignored' => new Expr\Variable('attribute')]);

        self::assertSame([$condition, $statement, $elseif, $else], ChildNodes::of($node));
    }

    public function testArrayHolesAreSkippedAndResultsAreAList(): void
    {
        $first = new ArrayItem(new Scalar\Int_(1));
        $last = new ArrayItem(new Scalar\Int_(2));
        $node = new Expr\Array_([2 => $first, 4 => null, 9 => $last]);

        self::assertSame([$first, $last], ChildNodes::of($node));
        self::assertSame([], ChildNodes::of(new Expr\Array_()));
    }

    public function testScalarSubNodesAreNotChildrenButDynamicNamesAre(): void
    {
        $name = new Expr\Variable('name');

        self::assertSame([$name], ChildNodes::of(new Expr\Variable($name)));
        self::assertSame([], ChildNodes::of(new Expr\Variable('literal')));
        self::assertSame([], ChildNodes::of(new Scalar\Int_(42)));
    }
}
