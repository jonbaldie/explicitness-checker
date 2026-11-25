<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Tests\Support\Process;
use JonBaldie\ExplicitnessChecker\Tests\Support\RuleList;
use JonBaldie\ExplicitnessChecker\Walk\AccessRules;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

/**
 * #57: AccessRules asks its rules in order and the first that claims a node
 * wins, so its order would matter wherever two rules claim the same node.
 */
class AccessRulesTest extends TestCase
{
    /**
     * No node is claimed by more than one rule, so the order of the list
     * never decides anything. Every node of every fixture and of src is asked.
     */
    public function testNoTwoRulesClaimTheSameNode(): void
    {
        $rules = RuleList::flattened(new AccessRules());
        $overlaps = [];
        $asked = 0;
        foreach ($this->corpus() as $node) {
            foreach ([false, true] as $isWrite) {
                $claimants = [];
                foreach ($rules as $rule) {
                    if ($rule->children($node, $isWrite) !== null) {
                        $claimants[] = $rule::class;
                    }
                }
                $asked++;
                if (count($claimants) > 1) {
                    $overlaps[$node->getType() . ': ' . implode(', ', $claimants)] = true;
                }
            }
        }

        self::assertGreaterThan(1000, $asked);
        self::assertSame([], array_keys($overlaps));
    }

    /**
     * A node no rule claims walks every child in its own mode.
     */
    public function testUnclaimedNodeWalksEverySubNodeInItsOwnMode(): void
    {
        $sum = $this->firstStatement('$a + $b;');
        self::assertInstanceOf(Stmt\Expression::class, $sum);
        self::assertInstanceOf(Expr\BinaryOp\Plus::class, $sum->expr);

        self::assertSame(
            [[$sum->expr->left, true], [$sum->expr->right, true]],
            (new AccessRules())->childrenOf($sum->expr, true),
        );
        self::assertSame(
            [[$sum->expr->left, false], [$sum->expr->right, false]],
            (new AccessRules())->childrenOf($sum->expr, false),
        );
    }

    /**
     * #207: a call no other rule claims walks every child as a read, even
     * when the call result is a write target.
     */
    public function testUnclaimedCallWalksEverySubNodeAsRead(): void
    {
        $rules = new AccessRules();

        $call = $this->firstStatement('f($a, $b);');
        self::assertInstanceOf(Stmt\Expression::class, $call);
        self::assertInstanceOf(Expr\FuncCall::class, $call->expr);
        self::assertSame(
            [[$call->expr->name, false], [$call->expr->args[0], false], [$call->expr->args[1], false]],
            $rules->childrenOf($call->expr, true),
        );
        self::assertSame(
            [[$call->expr->name, false], [$call->expr->args[0], false], [$call->expr->args[1], false]],
            $rules->childrenOf($call->expr, false),
        );

        $method = $this->firstStatement('$o->m($a);');
        self::assertInstanceOf(Stmt\Expression::class, $method);
        self::assertInstanceOf(Expr\MethodCall::class, $method->expr);
        self::assertSame(
            [[$method->expr->var, false], [$method->expr->name, false], [$method->expr->args[0], false]],
            $rules->childrenOf($method->expr, true),
        );

        $nullsafe = $this->firstStatement('$o?->m($a);');
        self::assertInstanceOf(Stmt\Expression::class, $nullsafe);
        self::assertInstanceOf(Expr\NullsafeMethodCall::class, $nullsafe->expr);
        self::assertSame(
            [[$nullsafe->expr->var, false], [$nullsafe->expr->name, false], [$nullsafe->expr->args[0], false]],
            $rules->childrenOf($nullsafe->expr, true),
        );

        $new = $this->firstStatement('new C($a);');
        self::assertInstanceOf(Stmt\Expression::class, $new);
        self::assertInstanceOf(Expr\New_::class, $new->expr);
        self::assertSame(
            [[$new->expr->class, false], [$new->expr->args[0], false]],
            $rules->childrenOf($new->expr, true),
        );

        $static = $this->firstStatement('C::s($a);');
        self::assertInstanceOf(Stmt\Expression::class, $static);
        self::assertInstanceOf(Expr\StaticCall::class, $static->expr);
        self::assertSame(
            [[$static->expr->class, false], [$static->expr->name, false], [$static->expr->args[0], false]],
            $rules->childrenOf($static->expr, true),
        );
    }

    /**
     * A claimed node walks only what its rule says, never every sub-node too.
     */
    public function testClaimedNodeNeverFallsThroughToEverySubNode(): void
    {
        $rules = new AccessRules();
        $closure = $this->firstStatement('$f = function ($x) { return $x; };');
        self::assertInstanceOf(Stmt\Expression::class, $closure);
        self::assertInstanceOf(Expr\Assign::class, $closure->expr);
        $static = $this->firstStatement('static $a, $b = 1;');
        self::assertInstanceOf(Stmt\Static_::class, $static);

        self::assertSame([], $rules->childrenOf($closure->expr->expr, false));
        self::assertSame([[$static->vars[1]->default, false]], $rules->childrenOf($static, false));
    }

    protected function firstStatement(string $source): Node
    {
        return ((new ParserFactory())->createForHostVersion()->parse('<?php ' . $source) ?? [])[0];
    }

    /**
     * @return array<Node> every node of every parseable PHP file in tests/Fixtures and src
     */
    protected function corpus(): array
    {
        $parser = (new ParserFactory())->createForHostVersion();
        $nodes = [];
        foreach (['tests/Fixtures', 'src'] as $directory) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(Process::ROOT . '/' . $directory));
            foreach ($files as $file) {
                if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }
                try {
                    $statements = $parser->parse((string) file_get_contents($file->getPathname())) ?? [];
                } catch (Error) {
                    continue;
                }
                $nodes = array_merge($nodes, (new NodeFinder())->find($statements, fn (Node $node): bool => true));
            }
        }

        return $nodes;
    }
}
