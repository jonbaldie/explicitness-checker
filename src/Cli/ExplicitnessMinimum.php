<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

/**
 * The --min-explicitness threshold: the lowest percentage of checked
 * function-likes that must be explicit for the run to pass.
 */
class ExplicitnessMinimum
{
    public function __construct(protected string $value)
    {
    }

    /**
     * Why the minimum is unusable: it is not a plain decimal number from 0
     * to 100. Null when it is valid.
     */
    public function error(): ?string
    {
        $valid = preg_match('/^\d+(\.\d+)?\z/', $this->value) === 1
            && (strlen(ltrim($this->parts()[0], '0')) <= 2 || (string) $this === '100');

        return $valid ? null : "Invalid --min-explicitness: {$this->value}";
    }

    /**
     * Whether explicit ÷ checked, as a percentage, is at or above the minimum.
     * A run that checked nothing is 100% explicit.
     *
     * Compares exactly, by long division against the minimum's digits: the
     * surplus is (explicit × 100 ÷ checked − the minimum so far) × checked,
     * scaled up by ten for each decimal place. Once it is negative the
     * minimum is out of reach; once it is at least checked, the remaining
     * digits can't close the gap.
     */
    public function isMetBy(int $explicit, int $checked): bool
    {
        [$whole, $fraction] = $this->parts();
        $surplus = $explicit * 100 - (int) $whole * $checked;
        for ($place = 0; $place < strlen($fraction) && $surplus >= 0 && $surplus < $checked; $place++) {
            $surplus = $surplus * 10 - (int) $fraction[$place] * $checked;
        }

        return $surplus >= 0;
    }

    /**
     * The minimum without leading or trailing zeros, e.g. "87.5" for "087.50".
     */
    public function __toString(): string
    {
        [$whole, $fraction] = $this->parts();
        $whole = ltrim($whole, '0');
        $fraction = rtrim($fraction, '0');

        return ($whole === '' ? '0' : $whole) . ($fraction === '' ? '' : '.' . $fraction);
    }

    /**
     * @return array{string, string} the digits before and after the decimal point
     */
    protected function parts(): array
    {
        $parts = explode('.', $this->value);

        return [$parts[0], $parts[1] ?? ''];
    }
}
