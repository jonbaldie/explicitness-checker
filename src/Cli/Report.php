<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

/**
 * Prints the results table and severity summary, and decides the exit code:
 * 0 with no violations or analysis failures, otherwise the exit code of the
 * highest severity found, with parse failures forcing at least 2.
 * With a --min-explicitness threshold it also prints the percentage of checked
 * function-likes that are explicit, and when that meets the threshold the
 * violations no longer set the exit code; parse failures still do.
 */
class Report
{
    protected const HEADERS = ['File', 'Line', 'Function', 'Implicit Inputs', 'Implicit Outputs', 'Severity'];

    public function __construct(protected Console $console)
    {
    }

    /**
     * @param list<Violation> $violations
     * @param int             $checked    how many function-likes were checked
     *
     * @return int exit code
     */
    public function print(array $violations, bool $hasParseErrors, int $checked, ?ExplicitnessMinimum $minimum): int
    {
        $counts = array_fill_keys(array_keys(Severity::EXIT_CODES), 0);
        if ($violations === []) {
            $this->console->out("No implicit inputs or outputs found.\n");
            if ($minimum !== null) {
                $this->console->out($this->explicitness($checked, $checked, $minimum) . "\n");
            }

            return $this->exitCode($counts, $hasParseErrors);
        }

        $rows = [self::HEADERS];
        foreach ($violations as $violation) {
            $severity = $violation->getSeverity();
            $counts[$severity]++;
            $rows[] = [
                $violation->getFile(),
                (string) $violation->getLine(),
                $violation->getFunction(),
                implode('; ', $violation->getInputs()),
                implode('; ', $violation->getOutputs()),
                ucfirst($severity),
            ];
        }
        $explicit = $checked - count($violations);
        $exitCode = $this->exitCode($counts, $hasParseErrors);
        if ($minimum !== null && $minimum->isMetBy($explicit, $checked)) {
            $exitCode = $this->exitCode(array_fill_keys(array_keys(Severity::EXIT_CODES), 0), $hasParseErrors);
        }

        $this->console->out("Analyzing...\n\nResults:\n\n");
        $this->printTable($rows);
        $this->console->out("Summary:\n");
        foreach (array_reverse(Severity::EXIT_CODES, true) as $severity => $code) {
            $this->console->out('  ' . ucfirst($severity) . " violations: {$counts[$severity]} (exit code {$code})\n");
        }
        $this->console->out("  Exit code: {$exitCode}\n");
        if ($minimum !== null) {
            $this->console->out('  ' . $this->explicitness($explicit, $checked, $minimum) . "\n");
        }
        $this->console->out("\n");

        return $exitCode;
    }

    /**
     * E.g. "Explicit function-likes: 2 of 3 (66.6%, minimum 50%)". The
     * percentage is rounded down to one decimal place; checking nothing is 100%.
     */
    protected function explicitness(int $explicit, int $checked, ExplicitnessMinimum $minimum): string
    {
        $tenths = $checked === 0 ? 1000 : intdiv(1000 * $explicit, $checked);

        return sprintf(
            'Explicit function-likes: %d of %d (%d.%d%%, minimum %s%%)',
            $explicit,
            $checked,
            intdiv($tenths, 10),
            $tenths % 10,
            $minimum,
        );
    }

    /**
     * @param array<string, int> $counts violations per severity
     */
    protected function exitCode(array $counts, bool $hasParseErrors = false): int
    {
        $exitCode = 0;
        foreach (Severity::EXIT_CODES as $severity => $code) {
            if ($counts[$severity] > 0) {
                $exitCode = $code;
            }
        }

        if ($hasParseErrors) {
            $exitCode = max($exitCode, Severity::EXIT_CODES[Severity::SERIOUS]);
        }

        return $exitCode;
    }

    /**
     * @param non-empty-list<list<string>> $rows the header row, then one row per violation
     */
    protected function printTable(array $rows): void
    {
        $widths = array_map(
            static fn (int $column): int => max(0, ...array_map('strlen', array_column($rows, $column))),
            array_keys(self::HEADERS),
        );

        $separator = '+';
        foreach ($widths as $width) {
            $separator .= str_repeat('-', $width + 2) . '+';
        }
        $separator .= "\n";

        $header = array_shift($rows);
        $this->console->out($separator);
        $this->console->out($this->formatRow($header, $widths));
        $this->console->out($separator);
        foreach ($rows as $row) {
            $this->console->out($this->formatRow($row, $widths));
        }
        $this->console->out($separator . PHP_EOL);
    }

    /**
     * @param list<string> $row
     * @param list<int>    $widths
     */
    protected function formatRow(array $row, array $widths): string
    {
        $line = '|';
        foreach ($row as $column => $cell) {
            $line .= ' ' . str_pad($cell, $widths[$column]) . ' |';
        }

        return $line . "\n";
    }
}
