<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests\Support;

use JonBaldie\ExplicitnessChecker\Cli\ArgumentParser;
use JonBaldie\ExplicitnessChecker\Cli\FileChecker;
use JonBaldie\ExplicitnessChecker\Cli\Violation;
use JonBaldie\ExplicitnessChecker\SourceChecker;
use PHPUnit\Framework\Assert;

/**
 * Checks one file as the CLI does, through FileChecker, and returns the
 * report's rows as data rather than rendered table cells (#56).
 */
class CheckedFile
{
    /**
     * The rows the CLI would report for the file, in source order. Fails the
     * test when the file cannot be read or parsed.
     *
     * @param list<string> $flags CLI flags, e.g. `--strict`, read by the CLI's own parser
     *
     * @return list<Violation>
     */
    public static function violations(string $path, array $flags = []): array
    {
        $options = (new ArgumentParser())->parse(array_merge(['bin/explicitness-checker'], $flags, [$path]));
        Assert::assertNotNull($options);

        $result = (new FileChecker(new SourceChecker(), $options->getMode()))->check($path);
        Assert::assertFalse($result->isUnreadable(), "Cannot read {$path}");
        Assert::assertNull($result->getParseError(), "Cannot parse {$path}");

        return $result->getViolations();
    }

    /**
     * @param list<string> $flags
     *
     * @return list<array{int, string, list<string>, list<string>}> [line, function, inputs, outputs]
     */
    public static function rows(string $path, array $flags = []): array
    {
        return array_map(
            static fn (Violation $violation): array => [
                $violation->getLine(),
                $violation->getFunction(),
                $violation->getInputs(),
                $violation->getOutputs(),
            ],
            self::violations($path, $flags),
        );
    }
}
