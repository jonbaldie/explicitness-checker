<?php

declare(strict_types=1);

use JonBaldie\ExplicitnessChecker\Finding;
use JonBaldie\ExplicitnessChecker\Mode;
use JonBaldie\ExplicitnessChecker\SourceChecker;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

/** @return Generator<int, list<string>> */
function programs(array $actions, int $maxLength): Generator
{
    yield [];
    $layer = [[]];
    for ($length = 1; $length <= $maxLength; $length++) {
        $next = [];
        foreach ($layer as $prefix) {
            foreach ($actions as $action) {
                $program = [...$prefix, $action];
                $next[] = $program;
                yield $program;
            }
        }
        $layer = $next;
    }
}

function expectSame(string $case, mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException("FAIL {$case}\nexpected: " . var_export($expected, true) . "\nactual: " . var_export($actual, true));
    }
}

/** @return list<string> */
function outputDescriptions(SourceChecker $checker, string $body, string $parameters = '$param, $flag'): array
{
    $result = $checker->check('<?php function example(' . $parameters . ') {' . $body . '}', new Mode(false, false))[0];

    return array_map(static fn (Finding $finding): string => $finding->getDescription(), $result->getOutputs());
}
