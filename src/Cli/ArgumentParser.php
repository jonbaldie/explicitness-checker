<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

use JonBaldie\ExplicitnessChecker\Mode;

/**
 * Turns the command line into Options.
 *
 * Switches can appear anywhere. Value options take their value either after
 * "=" or as the next argument; a value option with nothing after it is
 * ignored. "--exclude" accumulates on top of the default "vendor"; for the
 * two patterns the last one given wins. The first argument that does not
 * start with "-" is the path; later ones and unknown options are ignored.
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
            if ($path === null && !str_starts_with($argument, '-')) {
                $path = $argument;
            }
        }

        if ($path === null) {
            return null;
        }

        return new Options(
            $path,
            $switches['verbose'],
            new Mode($switches['strict'], $switches['props']),
            new FileFilter($values['--exclude'], $this->last($values['--include-pattern']), $this->last($values['--exclude-pattern'])),
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

    /**
     * @param list<string> $values
     */
    protected function last(array $values): ?string
    {
        return $values === [] ? null : $values[count($values) - 1];
    }
}
