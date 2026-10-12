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
    protected const USAGE = "Usage: explicitness-checker [-h|--help] [-v|--verbose] [--strict] [--props] [--exclude=dir] [--include-pattern=pattern] [--exclude-pattern=pattern] [--min-explicitness=percent] /path/to/project\n";

    protected const HELP = self::USAGE . "\nOptions:\n  -h, --help                          Show this help message and exit.\n  -v, --verbose                       Enable verbose output showing detailed analysis progress.\n      --strict                        Enable strict mode to detect additional implicit I/O patterns.\n      --props                         Also report instance property access (\$this->property).\n      --exclude=<dir>                 Exclude specific directories from analysis (default: vendor). Can be specified multiple times.\n      --include-pattern=<pattern>     Only analyze files matching regex pattern. Can be specified multiple times.\n      --exclude-pattern=<pattern>     Exclude files matching regex pattern. Can be specified multiple times.\n      --min-explicitness=<percent>    Require a minimum percentage of explicit function-likes (0-100).\n\nArguments:\n  /path/to/project                    Path to a PHP file or directory to check.\n";

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
        if ($options->isHelp()) {
            return $this->help();
        }
        $problem = $options->problem();
        if ($problem !== null) {
            return $this->usageError($problem);
        }

        return $this->check($options);
    }

    /**
     * Prints the help message to stdout.
     *
     * @return int exit code 0
     */
    protected function help(): int
    {
        fwrite($this->stdout, self::HELP);

        return 0;
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

        $discovered = (new PhpFileFinder($options->getFilter(), $console))->find($path);
        $files = $discovered->getFiles();
        $console->verbose('Found ' . count($files) . ' PHP file(s).');
        if ($files === []) {
            $console->out("No PHP files found in {$path}\n");

            return RunSummary::of($discovered, [], $options->getMinimum())->getExitCode();
        }

        $checker = new FileChecker(new SourceChecker(), $mode);
        $narrator = new FileNarrator($console);
        $results = [];
        foreach ($files as $file) {
            $result = $checker->check($file);
            $narrator->narrate($result);
            $results[] = $result;
        }

        $summary = RunSummary::of($discovered, $results, $options->getMinimum());
        (new Report($console))->print($summary);

        return $summary->getExitCode();
    }
}
