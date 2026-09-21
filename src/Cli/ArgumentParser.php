<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

use JonBaldie\ExplicitnessChecker\Mode;

/**
 * Turns the command line into Options.
 *
 * Switches can appear anywhere. Value options take their value either after
 * "=" or as the next argument; a value option with nothing after it is
 * ignored. Every value option accumulates: "--exclude" on top of the default
 * "vendor", and each pattern option over its earlier occurrences. The first
 * argument that does not start with "-" is the path; later ones are ignored.
 * Any other argument starting with "-" is an unknown option and stops the
 * parse, so a mistyped flag cannot quietly change what is checked.
 */
class ArgumentParser
{
    protected const SWITCHES = [
        '-v' => 'verbose',
        '--verbose' => 'verbose',
        '--strict' => 'strict',
        '--props' => 'props',
    ];

    protected const VALUE_OPTIONS = ['--exclude', '--include-pattern', '--exclude-pattern'];

    /**
     * @param list<string> $argv CLI arguments, including the script name
     *
     * @return Options|null null when no path was given
     *
     * @throws \InvalidArgumentException on an unknown option
     */
    public function parse(array $argv): ?Options
    {
        $switches = ['verbose' => false, 'strict' => false, 'props' => false];
        $values = ['--exclude' => ['vendor'], '--include-pattern' => [], '--exclude-pattern' => []];
        $path = null;

        $arguments = array_slice($argv, 1);
        while ($arguments !== []) {
            $argument = array_shift($arguments);
            if (isset(self::SWITCHES[$argument])) {
                $switches[self::SWITCHES[$argument]] = true;
                continue;
            }
            $option = $this->valueOption($argument, $arguments);
            if ($option !== null) {
                $values[$option[0]][] = $option[1];
                continue;
            }
            if (in_array($argument, self::VALUE_OPTIONS, true)) {
                continue;
            }
            if (str_starts_with($argument, '-')) {
                throw new \InvalidArgumentException("Unknown option: {$argument}");
            }
            $path ??= $argument;
        }

        if ($path === null) {
            return null;
        }

        return new Options(
            $path,
            $switches['verbose'],
            new Mode($switches['strict'], $switches['props']),
            new FileFilter($values['--exclude'], $values['--include-pattern'], $values['--exclude-pattern']),
        );
    }

    /**
     * Reads "--name=value", or "--name value" (taking the value off the
     * remaining arguments).
     *
     * @param list<string> $remaining
     *
     * @return array{string, string}|null the option name and its value, or null if
     *                                    this is not a value option with a value
     */
    protected function valueOption(string $argument, array &$remaining): ?array
    {
        foreach (self::VALUE_OPTIONS as $name) {
            if (str_starts_with($argument, $name . '=')) {
                return [$name, substr($argument, strlen($name) + 1)];
            }
            if ($argument === $name && $remaining !== []) {
                return [$name, array_shift($remaining)];
            }
        }

        return null;
    }
}
