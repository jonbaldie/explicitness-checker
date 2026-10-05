<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

/**
 * Prints a run's results table and severity summary, and with a
 * --min-explicitness threshold, the percentage of checked function-likes that
 * are explicit.
 */
class Report
{
    protected const HEADERS = ['File', 'Line', 'Function', 'Implicit Inputs', 'Implicit Outputs', 'Severity'];

    public function __construct(protected Console $console)
    {
    }

    public function print(RunSummary $summary): void
    {
        $violations = $summary->getViolations();
        $minimum = $summary->getMinimum();
        if ($violations === []) {
            $this->console->out("No implicit inputs or outputs found.\n");
            if ($minimum !== null) {
                $this->console->out($this->explicitness($summary, $minimum) . "\n");
            }

            return;
        }

        $rows = [self::HEADERS];
        foreach ($violations as $violation) {
            $rows[] = [
                $violation->getFile(),
                (string) $violation->getLine(),
                $violation->getFunction(),
                implode('; ', $violation->getInputs()),
                implode('; ', $violation->getOutputs()),
                ucfirst($violation->getSeverity()),
            ];
        }

        $this->console->out("Analyzing...\n\nResults:\n\n");
        $this->printTable($rows);
        $this->console->out("Summary:\n");
        $counts = $summary->getSeverityCounts();
        foreach (array_reverse(Severity::EXIT_CODES, true) as $severity => $code) {
            $this->console->out('  ' . ucfirst($severity) . " violations: {$counts[$severity]} (exit code {$code})\n");
        }
        $this->console->out("  Exit code: {$summary->getExitCode()}\n");
        if ($minimum !== null) {
            $this->console->out('  ' . $this->explicitness($summary, $minimum) . "\n");
        }
        $this->console->out("\n");
    }

    /**
     * E.g. "Explicit function-likes: 2 of 3 (66.6%, minimum 50%)".
     */
    protected function explicitness(RunSummary $summary, ExplicitnessMinimum $minimum): string
    {
        $tenths = $summary->getExplicitnessTenths();

        return sprintf(
            'Explicit function-likes: %d of %d (%d.%d%%, minimum %s%%)',
            $summary->getExplicitCount(),
            $summary->getCheckedCount(),
            intdiv($tenths, 10),
            $tenths % 10,
            $minimum,
        );
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
