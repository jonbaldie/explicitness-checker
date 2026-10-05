<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

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
        $unchecked = $result->getUnchecked();
        if ($unchecked !== null) {
            $this->narrateUnchecked($unchecked);
        }

        foreach ($result->getFunctions() as $function) {
            $this->narrateFunction($function);
        }
    }

    protected function narrateUnchecked(UncheckedInput $unchecked): void
    {
        $file = $unchecked->getPath();
        if ($unchecked->getReason() === UncheckedInput::UNPARSEABLE_FILE) {
            $this->console->error("Parse error in {$file}: {$unchecked->getDetail()}" . PHP_EOL);

            return;
        }
        $this->console->error("Cannot read file: {$file}" . PHP_EOL);
    }

    protected function narrateFunction(FunctionResult $function): void
    {
        $name = $function->getName();
        $this->console->verbose("Analyzing function/method: {$name} (line {$function->getLine()})");
        $analysis = $function->getAnalysis();
        $this->verboseList("  Declared globals in {$name}: ", ', ', $analysis->getDeclaredGlobals());
        $this->verboseList("  Parameters for {$name}: ", ', ', $analysis->getParameters());

        $inputs = Violation::descriptions($function->getInputs());
        $outputs = Violation::descriptions($function->getOutputs());
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
}
