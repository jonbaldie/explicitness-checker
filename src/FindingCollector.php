<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker;

use PhpParser\Node;

/**
 * Accumulates findings for one function-like, keeping the first occurrence of
 * each description in the order it was found, inputs and outputs together.
 *
 * One map serves both directions: every description names its direction
 * ("read from …" or "wrote to …", "reads …" or "writes …"), so an input and an
 * output never share one.
 */
class FindingCollector
{
    /** @var array<string, Finding> */
    protected array $findings = [];

    /**
     * Record a read ("read from <subject>") or a write ("wrote to <subject>"),
     * of the named variable if there is one.
     */
    public function access(bool $isWrite, string $subject, string $category, Node $node, ?string $variable = null): void
    {
        if ($isWrite) {
            $this->output('wrote to ' . $subject, $category, $node, $variable);

            return;
        }

        $this->input('read from ' . $subject, $category, $node, $variable);
    }

    public function input(string $description, string $category, Node $node, ?string $variable = null): void
    {
        $this->record($description, $category, $node, $variable, false);
    }

    public function output(string $description, string $category, Node $node, ?string $variable = null): void
    {
        $this->record($description, $category, $node, $variable, true);
    }

    /**
     * Distinct inputs and outputs, in order of first occurrence.
     *
     * @return list<Finding>
     */
    public function findings(): array
    {
        return array_values($this->findings);
    }

    protected function record(string $description, string $category, Node $node, ?string $variable, bool $isOutput): void
    {
        $this->findings[$description] ??= new Finding($description, $category, $node->getStartLine(), $variable, $isOutput);
    }
}
