<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Walk;

use JonBaldie\ExplicitnessChecker\Scope\ScopeBoundary;
use JonBaldie\ExplicitnessChecker\VariableName;
use PhpParser\Node;
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
        $names = [];

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
            if ($node instanceof Stmt\Global_) {
                foreach ($this->declaredNames($node) as $name) {
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

        return $names;
    }

    /**
     * @return list<string>
     */
    protected function declaredNames(Stmt\Global_ $global): array
    {
        $names = [];
        foreach ($global->vars as $var) {
            $name = VariableName::of($var);
            if ($name !== null) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
