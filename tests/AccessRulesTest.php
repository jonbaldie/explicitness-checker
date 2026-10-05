<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Tests\Support\Process;
use JonBaldie\ExplicitnessChecker\Tests\Support\RuleList;
use JonBaldie\ExplicitnessChecker\Walk\AccessRules;
use JonBaldie\ExplicitnessChecker\Walk\SubNodes;
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
        $call = $this->firstStatement('f($a, $b);');
        self::assertInstanceOf(Stmt\Expression::class, $call);
        [$name, $first, $second] = SubNodes::of($call->expr);

        self::assertSame(
            [[$name, true], [$first, true], [$second, true]],
            (new AccessRules())->childrenOf($call->expr, true),
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
