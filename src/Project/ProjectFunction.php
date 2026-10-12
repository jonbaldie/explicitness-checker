<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Project;

use JonBaldie\ExplicitnessChecker\FunctionResult;
use PhpParser\Node;

final class ProjectFunction
{
    public function __construct(
        protected string $id,
        protected Node\FunctionLike $node,
        protected FunctionResult $result,
        protected ?string $class = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getNode(): Node\FunctionLike
    {
        return $this->node;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    public function getResult(): FunctionResult
    {
        return $this->result;
    }
}
