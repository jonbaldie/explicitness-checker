<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

/**
 * Prints the results table and severity summary, and decides the exit code:
 * 0 with no violations, otherwise the exit code of the highest severity found.
 */
class Report
{
    protected const HEADERS = ['File', 'Line', 'Function', 'Implicit Inputs', 'Implicit Outputs', 'Severity'];

    public function __construct(protected Console $console)
    {
    }

    /**
     * @param list<Violation> $violations
     *
     * @return int exit code
     */
    public function print(array $violations): int
    {
        if ($violations === []) {
            $this->console->out("No implicit inputs or outputs found.\n");

            return 0;
        }

        $counts = array_fill_keys(array_keys(Severity::EXIT_CODES), 0);
        $rows = [self::HEADERS];
        foreach ($violations as $violation) {
            $severity = $violation->getSeverity();
            $counts[$severity]++;
            $rows[] = [
                basename($violation->getFile()),
                (string) $violation->getLine(),
                $violation->getFunction(),
                implode('; ', $violation->getInputs()),
                implode('; ', $violation->getOutputs()),
                ucfirst($severity),
            ];
        }
        $exitCode = $this->exitCode($counts);

        $this->console->out("Analyzing...\n\nResults:\n\n");
        $this->printTable($rows);
        $this->console->out("Summary:\n");
        $this->console->out("  Critical violations: {$counts[Severity::CRITICAL]} (exit code 3)\n");
        $this->console->out("  Serious violations: {$counts[Severity::SERIOUS]} (exit code 2)\n");
        $this->console->out("  Minor violations: {$counts[Severity::MINOR]} (exit code 1)\n");
        $this->console->out("  Exit code: {$exitCode}\n\n");

        return $exitCode;
    }

    /**
     * @param array<string, int> $counts violations per severity
     */
    protected function exitCode(array $counts): int
    {
        $exitCode = 0;
        foreach (Severity::EXIT_CODES as $severity => $code) {
            if ($counts[$severity] > 0) {
                $exitCode = $code;
            }
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
