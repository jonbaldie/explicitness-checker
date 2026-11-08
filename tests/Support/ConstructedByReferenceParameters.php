<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests\Support;

use JonBaldie\ExplicitnessChecker\Walk\ReflectedByReferenceParameters;

class ConstructedByReferenceParameters extends ReflectedByReferenceParameters
{
    public function __construct(public string $label)
    {
    }
}
