<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

use JonBaldie\ExplicitnessChecker\Analyser;
use JonBaldie\ExplicitnessChecker\Mode;
use JonBaldie\ExplicitnessChecker\Scope\FunctionLikeFinder;
use PhpParser\ParserFactory;

/**
 * The explicitness-checker command: checks the PHP files under a path for
 * implicit inputs and outputs, prints a report, and returns the exit code.
 *
 * Exit codes: 0 clean or no PHP files, 1 minor, 2 serious or bad usage,
 * 3 critical.
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
        $options = (new ArgumentParser())->parse($argv);
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
     * directory, or a filter pattern that does not compile. Null when the
     * analysis can go ahead.
     */
    protected function problem(Options $options): ?string
    {
        $path = $options->getPath();
        if (!is_dir($path) && !is_file($path)) {
            return "Path not found: {$path}";
        }

        return $options->getFilter()->patternError();
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

        $checker = new FileChecker(
            (new ParserFactory())->createForNewestSupportedVersion(),
            new Analyser(),
            new FunctionLikeFinder(),
            $console,
            $mode,
        );
        $violations = [];
        foreach ($files as $file) {
            $violations = array_merge($violations, $checker->check($file));
        }

        return (new Report($console))->print($violations);
    }
}
