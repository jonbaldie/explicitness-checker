<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

use JonBaldie\ExplicitnessChecker\Finding;
use JonBaldie\ExplicitnessChecker\FunctionResult;

/**
 * Tells the user what checking one file found: a diagnostic on standard
 * error when it could not be read or parsed, and verbose progress lines for
 * each function-like.
 */
class FileNarrator
{
    public function __construct(protected Console $console)
    {
    }

    public function narrate(FileCheckResult $result): void
    {
        $file = $result->getFile();
        $this->console->verbose("Parsing file: {$file}");
        if ($result->isUnreadable()) {
            $this->console->error("Cannot read file: {$file}" . PHP_EOL);
        }
        $parseError = $result->getParseError();
        if ($parseError !== null) {
            $this->console->error("Parse error in {$file}: {$parseError}" . PHP_EOL);
        }

        foreach ($result->getFunctions() as $function) {
            $this->function($function);
        }
    }

    protected function function(FunctionResult $function): void
    {
        $name = $function->getName();
        $this->console->verbose("Analyzing function/method: {$name} (line {$function->getLine()})");
        $analysis = $function->getAnalysis();
        $this->verboseList("  Declared globals in {$name}: ", ', ', $analysis->getDeclaredGlobals());
        $this->verboseList("  Parameters for {$name}: ", ', ', $analysis->getParameters());

        $inputs = self::descriptions($function->getInputs());
        $outputs = self::descriptions($function->getOutputs());
        if ($inputs === [] && $outputs === []) {
            $this->console->verbose("  No implicit inputs/outputs detected for {$name}");

            return;
        }

        $this->verboseList("  Implicit inputs for {$name}: ", '; ', $inputs);
        $this->verboseList("  Implicit outputs for {$name}: ", '; ', $outputs);
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

    /**
     * @param list<Finding> $findings
     *
     * @return list<string>
     */
    protected static function descriptions(array $findings): array
    {
        return array_map(static fn (Finding $finding): string => $finding->getDescription(), $findings);
    }
}
