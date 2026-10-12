<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Project;

final class CallEdge
{
    public function __construct(protected ProjectFunction $callee, protected int $line)
    {
    }

    public function getCallee(): ProjectFunction
    {
        return $this->callee;
    }

    public function getLine(): int
    {
        return $this->line;
    }
}
