<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker;

use PhpParser\Node;

/**
 * Accumulates findings for one function-like, keeping the first occurrence of
 * each description in the order it was found.
 */
class FindingCollector
{
    /** @var array<string, Finding> */
    protected array $inputs = [];

    /** @var array<string, Finding> */
    protected array $outputs = [];

    /**
     * Record a read ("read from <subject>") or a write ("wrote to <subject>").
     */
    public function access(bool $isWrite, string $subject, string $category, Node $node): void
    {
        if ($isWrite) {
            $this->output('wrote to ' . $subject, $category, $node);

            return;
        }

        $this->input('read from ' . $subject, $category, $node);
    }

    public function input(string $description, string $category, Node $node): void
    {
        $this->inputs[$description] ??= new Finding($description, $category, $node->getStartLine());
    }

    public function output(string $description, string $category, Node $node): void
    {
        $this->outputs[$description] ??= new Finding($description, $category, $node->getStartLine());
    }

    /**
     * @return list<Finding>
     */
    public function inputs(): array
    {
        return array_values($this->inputs);
    }

    /**
     * @return list<Finding>
     */
    public function outputs(): array
    {
        return array_values($this->outputs);
    }
}
