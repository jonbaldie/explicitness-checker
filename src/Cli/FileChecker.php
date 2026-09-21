<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

use JonBaldie\ExplicitnessChecker\FunctionResult;
use JonBaldie\ExplicitnessChecker\Mode;
use JonBaldie\ExplicitnessChecker\SourceChecker;
use PhpParser\Error;

/**
 * Parses one file and checks every function-like in it, in source order.
 *
 * Parsing, name resolution, discovery and analysis live in the shared
 * SourceChecker; this is what remains genuinely CLI: reading the file,
 * verbose messages, and turning results into Violations. Names are resolved
 * against the namespace and `use` imports as PHPStan resolves them, so the
 * CLI and the PHPStan rule report the same names. A file that fails to parse
 * is reported on standard error and skipped.
 */
class FileChecker
{
    public function __construct(
        protected SourceChecker $sourceChecker,
        protected Console $console,
        protected Mode $mode,
    ) {
    }

    /**
     * @return list<Violation>
     */
    public function check(string $file): array
    {
        $this->console->verbose("Parsing file: {$file}");
        $code = file_get_contents($file);
        if ($code === false) {
            $this->console->verbose("Failed to read file: {$file}");

            return [];
        }

        try {
            $results = $this->sourceChecker->check($code, $this->mode);
        } catch (Error $error) {
            $this->console->error("Parse error in {$file}: " . $error->getMessage() . PHP_EOL);

            return [];
        }

        $violations = [];
        foreach ($results as $result) {
            $violation = $this->violation($result, $file);
            if ($violation !== null) {
                $violations[] = $violation;
            }
        }

        return $violations;
    }

    protected function violation(FunctionResult $result, string $file): ?Violation
    {
        $name = $result->getName();
        $inputs = $result->getInputs();
        $outputs = $result->getOutputs();

        $this->console->verbose("Analyzing function/method: {$name} (line {$result->getLine()})");
        $this->verboseList("  Declared globals in {$name}: ", ', ', $result->getAnalysis()->getDeclaredGlobals());
        $this->verboseList("  Parameters for {$name}: ", ', ', $result->getAnalysis()->getParameters());

        if ($inputs === [] && $outputs === []) {
            $this->console->verbose("  No implicit inputs/outputs detected for {$name}");

            return null;
        }

        $violation = new Violation($file, $result->getLine(), $name, $inputs, $outputs);
        $this->verboseList("  Implicit inputs for {$name}: ", '; ', $violation->getInputs());
        $this->verboseList("  Implicit outputs for {$name}: ", '; ', $violation->getOutputs());

        return $violation;
    }

    /**
     * @param array<string> $items
     */
    protected function verboseList(string $label, string $glue, array $items): void
    {
        if ($items !== []) {
            $this->console->verbose($label . implode($glue, $items));
        }
    }
}
