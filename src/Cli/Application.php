<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

use JonBaldie\ExplicitnessChecker\Mode;
use JonBaldie\ExplicitnessChecker\SourceChecker;

/**
 * The explicitness-checker command: checks the PHP files under a path for
 * implicit inputs and outputs, prints a report, and returns the exit code.
 *
 * Exit codes: 0 clean or no PHP files, 1 minor, 2 serious or bad usage,
 * parse failure, 3 critical.
 */
class Application
{
    protected const USAGE = "Usage: explicitness-checker [-v|--verbose] [--strict] [--props] [--exclude=dir] [--include-pattern=pattern] [--exclude-pattern=pattern] /path/to/project\n";

    /**
     * @param resource $stdout
     * @param resource $stderr
     */
    public function __construct(protected $stdout, protected $stderr)
    {
    }

    /**
     * @param list<string> $argv CLI arguments, including the script name
     *
     * @return int exit code
     */
    public function run(array $argv): int
    {
        try {
            $options = (new ArgumentParser())->parse($argv);
        } catch (\InvalidArgumentException $unknownOption) {
            return $this->usageError($unknownOption->getMessage());
        }
        if ($options === null) {
            return $this->usageError(null);
        }
        $problem = $this->problem($options);
        if ($problem !== null) {
            return $this->usageError($problem);
        }

        return $this->check($options);
    }

    /**
     * Why the options cannot be used: a path that is neither a file nor a
     * directory, or a filter value/pattern that is invalid. Null when the
     * analysis can go ahead.
     */
    protected function problem(Options $options): ?string
    {
        $path = $options->getPath();
        if (!is_dir($path) && !is_file($path)) {
            return "Path not found: {$path}";
        }

        $filter = $options->getFilter();
        $directoryError = $filter->directoryError();
        if ($directoryError !== null) {
            return $directoryError;
        }

        return $filter->patternError();
    }

    /**
     * Reports bad usage: the reason, when there is one to give, then the usage
     * line, both on stderr.
     *
     * @return int the bad-usage exit code
     */
    protected function usageError(?string $reason): int
    {
        if ($reason !== null) {
            fwrite($this->stderr, $reason . "\n");
        }
        fwrite($this->stderr, self::USAGE);

        return 2;
    }

    protected function check(Options $options): int
    {
        $console = new Console($this->stdout, $this->stderr, $options->isVerbose());
        $path = $options->getPath();
        $mode = $options->getMode();

        $console->verbose("Starting analysis for path: {$path}");
        if ($mode->isStrict()) {
            $console->verbose('Strict mode enabled.');
        }
        if ($mode->isProps()) {
            $console->verbose('Props mode enabled.');
        }

        $files = (new PhpFileFinder($options->getFilter(), $console))->find($path);
        $console->verbose('Found ' . count($files) . ' PHP file(s).');
        if ($files === []) {
            $console->out("No PHP files found in {$path}\n");

            return 0;
        }

        $checker = new FileChecker(new SourceChecker(), $console, $mode);
        $violations = [];
        $hasParseErrors = false;
        foreach ($files as $file) {
            $result = $checker->check($file);
            $violations = array_merge($violations, $result->getViolations());
            $hasParseErrors = $hasParseErrors || $result->hasParseError();
        }

        return (new Report($console))->print($violations, $hasParseErrors);
    }
}
