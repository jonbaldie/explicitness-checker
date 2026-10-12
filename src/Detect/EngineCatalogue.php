<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Detect;

/**
 * Secure always reads entropy. Mt19937, Xoshiro256StarStar and
 * PcgOneseq128XslRr64 do when their seed is omitted or literal null. An
 * explicit seed, including zero or a variable, is the caller's input.
 */
class EngineCatalogue
{
    /**
     * @param array{string, bool, string} $randomRead
     *
     * @return list<array{string, bool, string}>
     */
    public function entries(string $lowerClass, CallArguments $arguments, array $randomRead): array
    {
        if ($lowerClass === 'random\engine\secure') {
            return [$randomRead];
        }
        if (!$this->isSeedable($lowerClass)) {
            return [];
        }

        return $arguments->omits(0, 'seed') ? [$randomRead] : [];
    }

    protected function isSeedable(string $lowerClass): bool
    {
        return in_array($lowerClass, [
            'random\engine\mt19937',
            'random\engine\xoshiro256starstar',
            'random\engine\pcgoneseq128xslrr64',
        ], true);
    }
}
