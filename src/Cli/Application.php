<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

use JonBaldie\ExplicitnessChecker\Analyser;
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
        if ($options !== null && !is_dir($options->getPath()) && !is_file($options->getPath())) {
            fwrite($this->stderr, "Path not found: {$options->getPath()}\n");
            $options = null;
        }
        if ($options === null) {
            fwrite($this->stderr, self::USAGE);

            return 2;
        }

        return $this->check($options);
    }

    protected function check(Options $options): int
    {
        $console = new Console($this->stdout, $this->stderr, $options->isVerbose());
        $path = $options->getPath();

        $console->verbose("Starting analysis for path: {$path}");
        if ($options->isStrict()) {
            $console->verbose('Strict mode enabled.');
        }
        if ($options->isProps()) {
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
            $console,
            $options->isStrict(),
            $options->isProps(),
        );
        $violations = [];
        foreach ($files as $file) {
            $violations = array_merge($violations, $checker->check($file));
        }

        return (new Report($console))->print($violations);
    }
}
