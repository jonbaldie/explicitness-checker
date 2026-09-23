<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests\Support;

/*
 * A user-defined function with a by-reference parameter, loaded into the test
 * process the way PHPStan loads the code it analyses (#72).
 */
function take_by_reference(mixed &$value): void
{
    $value = null;
}
