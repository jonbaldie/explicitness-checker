<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\FindingCollector;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;

/**
 * Strict mode: exit and die terminate the process, unless their argument is a
 * string literal, which PHP writes to standard output first.
 */
class ExitDetector implements Detector
{
    protected const NAMES = ['exit', 'die'];

    public function detect(Node $node, bool $isWrite, FindingCollector $findings): void
    {
        if ($node instanceof Expr\Exit_) {
            $name = $this->constructName($node);
            $argument = $node->expr;
        } elseif ($node instanceof Expr\FuncCall && $node->name instanceof Node\Name) {
            $name = $node->name->toString();
            if (!in_array($name, self::NAMES, true)) {
                return;
            }

            $argument = $node->args[0]->value ?? null;
        } else {
            return;
        }

        $description = $argument instanceof Scalar\String_
            ? 'writes to standard output'
            : 'terminates the program';
        $findings->output($description . ' (' . $name . ')', Category::STANDARD_OUTPUT, $node);
    }

    protected function constructName(Expr\Exit_ $node): string
    {
        return $node->getAttribute('kind', Expr\Exit_::KIND_EXIT) === Expr\Exit_::KIND_DIE
            ? 'die'
            : 'exit';
    }
}
