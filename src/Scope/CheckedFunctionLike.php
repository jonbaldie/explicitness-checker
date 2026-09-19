<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Scope;

use PhpParser\Node;

/**
 * A function-like with a body that should be checked, and its report name.
 */
class CheckedFunctionLike
{
    public function __construct(
        protected Node\FunctionLike $node,
        protected string $name,
    ) {
    }

    public function getNode(): Node\FunctionLike
    {
        return $this->node;
    }

    /**
     * The name per FunctionLikeNames, e.g. `App\Sub\K::m` or `{closure}`.
     */
    public function getName(): string
    {
        return $this->name;
    }
}
