<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Cli;

use JonBaldie\ExplicitnessChecker\Mode;
use JonBaldie\ExplicitnessChecker\SourceChecker;
use PhpParser\Error;

/**
 * Reads one file and checks every function-like in it, in source order.
 *
 * Parsing, name resolution, discovery and analysis live in the shared
 * SourceChecker; this adds reading the file and recording why it could not
 * be analysed. Names are resolved against the namespace and `use` imports as
 * PHPStan resolves them, so the CLI and the PHPStan rule report the same
 * names. It writes nothing: FileNarrator tells the user what happened.
 */
class FileChecker
{
    public function __construct(
        protected SourceChecker $sourceChecker,
        protected Mode $mode,
    ) {
    }

    public function check(string $file): FileCheckResult
    {
        $code = @file_get_contents($file);
        if ($code === false) {
            return new FileCheckResult($file, [], new UncheckedInput($file, UncheckedInput::UNREADABLE_FILE));
        }

        try {
            return new FileCheckResult($file, $this->sourceChecker->check($code, $this->mode));
        } catch (Error $error) {
            return new FileCheckResult(
                $file,
                [],
                new UncheckedInput($file, UncheckedInput::UNPARSEABLE_FILE, $error->getMessage()),
            );
        }
    }
}
