<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

/**
 * Where the CLI writes: the report to standard output, errors to standard
 * error, and "[verbose]" progress lines to standard output when enabled.
 */
class Console
{
    /**
     * @param resource $stdout
     * @param resource $stderr
     */
    public function __construct(
        protected $stdout,
        protected $stderr,
        protected bool $verbose,
    ) {
    }

    public function out(string $text): void
    {
        fwrite($this->stdout, $text);
    }

    public function error(string $text): void
    {
        fwrite($this->stderr, $text);
    }

    public function verbose(string $message): void
    {
        if ($this->verbose) {
            $this->out('[verbose] ' . $message . PHP_EOL);
        }
    }
}
