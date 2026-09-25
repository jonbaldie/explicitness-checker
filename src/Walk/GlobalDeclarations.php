<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use JonBaldie\ExplicitnessChecker\Scope\ScopeBoundary;
use JonBaldie\ExplicitnessChecker\VariableName;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use SplQueue;

/**
 * Collects the variable names a body declares with `global`, breadth-first.
 * Declarations inside nested function-likes and class-likes belong to those
 * scopes and are not collected.
 */
class GlobalDeclarations
{
    /**
     * @param array<Node> $stmts
     *
     * @return list<string> distinct names without "$", in discovery order
     */
    public function collect(array $stmts): array
    {
        return $this->collectWithDynamic($stmts)['names'];
    }

    /**
     * Collect literal names and whether the body declares a dynamic global.
     * Declarations inside nested function-likes and class-likes belong to
     * those scopes and are not collected.
     *
     * @param array<Node> $stmts
     *
     * @return array{names: list<string>, hasDynamicName: bool}
     */
    public function collectWithDynamic(array $stmts): array
    {
        $names = [];
        $hasDynamicName = false;

        /** @var SplQueue<Node> $queue */
        $queue = new SplQueue();
        foreach ($stmts as $stmt) {
            $queue->enqueue($stmt);
        }

        while (!$queue->isEmpty()) {
            $node = $queue->dequeue();
            if (ScopeBoundary::opensScope($node)) {
                continue;
            }
            $declared = $this->declaredNames($node);
            if ($declared !== null) {
                if ($this->declaresDynamicName($node)) {
                    $hasDynamicName = true;
                }

                foreach ($declared as $name) {
                    if (!in_array($name, $names, true)) {
                        $names[] = $name;
                    }
                }

                continue;
            }

            foreach (SubNodes::of($node) as $child) {
                $queue->enqueue($child);
            }
        }

        return ['names' => $names, 'hasDynamicName' => $hasDynamicName];
    }

    /**
     * @return list<string>|null null unless the node is a declaration
     */
    protected function declaredNames(Node $node): ?array
    {
        if (!$node instanceof Stmt\Global_) {
            return null;
        }

        $names = [];
        foreach ($node->vars as $var) {
            $name = VariableName::of($var);
            if ($name !== null) {
                $names[] = $name;
            }
        }

        return $names;
    }

    protected function declaresDynamicName(Node $node): bool
    {
        if (!$node instanceof Stmt\Global_) {
            return false;
        }

        foreach ($node->vars as $var) {
            if ($var instanceof Expr\Variable && !is_string($var->name)) {
                return true;
            }
        }

        return false;
    }
}
