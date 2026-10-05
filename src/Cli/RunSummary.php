<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

/**
 * The outcome of checking a set of files: the violations found, how explicit
 * the checked function-likes were, and the exit code.
 *
 * The exit code is 0 with no violations or analysis failures, otherwise the
 * exit code of the highest severity found, with parse failures forcing at
 * least 2. When a --min-explicitness threshold is met, the violations no
 * longer set the exit code; parse failures still do.
 */
class RunSummary
{
    /**
     * @param list<Violation> $violations
     * @param int             $checked    how many function-likes were checked
     */
    public function __construct(
        protected array $violations,
        protected bool $hasParseErrors,
        protected int $checked,
        protected ?ExplicitnessMinimum $minimum,
    ) {
    }

    /**
     * @return list<Violation>
     */
    public function getViolations(): array
    {
        return $this->violations;
    }

    /**
     * @return array<Severity::MINOR|Severity::SERIOUS|Severity::CRITICAL, int> violations per severity, lowest first
     */
    public function getSeverityCounts(): array
    {
        $counts = array_fill_keys(array_keys(Severity::EXIT_CODES), 0);
        foreach ($this->violations as $violation) {
            $counts[$violation->getSeverity()]++;
        }

        return $counts;
    }

    /**
     * How many function-likes were checked.
     */
    public function getCheckedCount(): int
    {
        return $this->checked;
    }

    /**
     * How many of the checked function-likes have no implicit inputs or outputs.
     */
    public function getExplicitCount(): int
    {
        return $this->checked - count($this->violations);
    }

    /**
     * The percentage of checked function-likes that are explicit, in tenths
     * and rounded down, e.g. 666 for 2 of 3; checking nothing is 1000.
     */
    public function getExplicitnessTenths(): int
    {
        return $this->checked === 0 ? 1000 : intdiv(1000 * $this->getExplicitCount(), $this->checked);
    }

    /**
     * The --min-explicitness threshold, or null when none was given.
     */
    public function getMinimum(): ?ExplicitnessMinimum
    {
        return $this->minimum;
    }

    /**
     * Whether enough of the checked function-likes are explicit; true when
     * there is no minimum.
     */
    public function isMinimumMet(): bool
    {
        return $this->minimum === null || $this->minimum->isMetBy($this->getExplicitCount(), $this->checked);
    }

    public function getExitCode(): int
    {
        $exitCode = 0;
        if ($this->minimum === null || !$this->isMinimumMet()) {
            foreach ($this->getSeverityCounts() as $severity => $count) {
                if ($count > 0) {
                    $exitCode = Severity::EXIT_CODES[$severity];
                }
            }
        }

        if ($this->hasParseErrors) {
            $exitCode = max($exitCode, Severity::EXIT_CODES[Severity::SERIOUS]);
        }

        return $exitCode;
    }
}
