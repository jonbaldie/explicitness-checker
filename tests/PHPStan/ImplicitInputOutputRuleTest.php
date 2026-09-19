<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests\PHPStan;

use JonBaldie\ExplicitnessChecker\Analyser;
use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\PHPStan\ImplicitInputOutputRule;
use JonBaldie\ExplicitnessChecker\Scope\FunctionLikeFinder;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The PHPStan rule on the fixtures (#16). Expected errors are what the CLI
 * reports on the same file with the matching flags, one per distinct input or
 * output per function-like, on the line where it first occurs.
 *
 * Set $strict and $props before calling analyse() to test other modes (#17,
 * #18).
 *
 * @extends RuleTestCase<ImplicitInputOutputRule>
 */
class ImplicitInputOutputRuleTest extends RuleTestCase
{
    protected const ROOT = __DIR__ . '/../..';
    protected const FIXTURES = self::ROOT . '/test-fixtures/';

    /**
     * What strict mode reports on strict-examples.php.
     */
    protected const STRICT_EXAMPLES_IN_STRICT_MODE = [
        [33, 'standardOutput', 'UserSession::greet writes to standard output (echo).'],
        [86, 'standardOutput', 'debug_user_data writes to standard output (var_dump).'],
        [94, 'standardOutput', 'inspect_and_die writes to standard output (print_r).'],
        [95, 'standardOutput', 'inspect_and_die writes to standard output (exit).'],
        [105, 'environment', 'get_database_url reads from environment variables (getenv).'],
        [113, 'environment', 'configure_environment writes to environment variables (putenv).'],
        [123, 'time', 'get_current_timestamp reads system time (time).'],
        [131, 'time', 'format_current_date reads system time (date).'],
        [139, 'time', 'benchmark_operation reads system time (microtime).'],
        [153, 'random', 'generate_random_id reads from random number generator (rand).'],
        [161, 'random', 'create_secure_token reads from random number generator (random_bytes).'],
        [171, 'random', 'seed_random_generator writes to random number generator state (mt_srand).'],
        [181, 'fileSystem', 'check_config_file reads from file system (file_exists).'],
        [190, 'fileSystem', 'get_file_info reads from file system (file_exists).'],
        [191, 'fileSystem', 'get_file_info reads from file system (is_file).'],
        [192, 'fileSystem', 'get_file_info reads from file system (filesize).'],
        [193, 'fileSystem', 'get_file_info reads from file system (filemtime).'],
        [202, 'fileSystem', 'list_config_files reads from file system (glob).'],
        [212, 'httpHeaders', 'send_json_response writes HTTP headers (header).'],
        [213, 'standardOutput', 'send_json_response writes to standard output (echo).'],
        [221, 'httpHeaders', 'set_user_preferences writes HTTP headers (setcookie).'],
        [221, 'time', 'set_user_preferences reads system time (time).'],
        [222, 'httpHeaders', 'set_user_preferences writes HTTP headers (header).'],
        [232, 'errorLog', 'log_user_action writes to error log (error_log).'],
        [241, 'errorLog', 'validate_input writes to error log (trigger_error).'],
        [256, 'session', 'initialize_user_session writes to session state (session_start).'],
        [265, 'session', 'get_session_info reads session state (session_id).'],
        [266, 'session', 'get_session_info reads session state (session_name).'],
        [275, 'session', 'logout_user writes to session state (session_destroy).'],
    ];

    protected bool $strict = false;

    protected bool $props = false;

    protected function getRule(): Rule
    {
        return new ImplicitInputOutputRule(new Analyser(), new FunctionLikeFinder(), $this->strict, $this->props);
    }

    public function testBadExamplesInDefaultMode(): void
    {
        $this->analyse([self::FIXTURES . 'bad-examples.php'], [
            ['uses_global_var read from global variable $some_global_number.', 34],
            ['uses_global_var wrote to global variable $some_global_number.', 36],
            ['uses_globals_array read from $GLOBALS[\'app_name\'].', 48],
            ['uses_globals_array wrote to $GLOBALS[\'app_name\'].', 50],
            ['reads_superglobals read from superglobal $_GET.', 61],
            ['reads_superglobals read from superglobal $_POST.', 62],
            ['writes_superglobals read from superglobal $_SESSION.', 73],
            ['writes_superglobals wrote to superglobal $_SESSION.', 74],
            ['writes_superglobals wrote to superglobal $_COOKIE.', 78],
            ['config_and_store_change read from global variable $config.', 89],
            ['config_and_store_change wrote to $GLOBALS[\'store\'].', 93],
            ['inc_global_counter read from global variable $some_global_number.', 116],
            ['inc_global_counter wrote to global variable $some_global_number.', 116],
            ['{closure} read from $GLOBALS[\'app_name\'].', 124],
            ['{closure} wrote to $GLOBALS[\'app_name\'].', 125],
        ]);
    }

    public function testGoodExamplesReportNothing(): void
    {
        $this->analyse([self::FIXTURES . 'good-examples.php'], []);
    }

    public function testStrictExamplesReportNothingInDefaultMode(): void
    {
        $this->analyse([self::FIXTURES . 'strict-examples.php'], []);
    }

    public function testBadExamplesInStrictMode(): void
    {
        $this->strict = true;

        $this->assertErrors('bad-examples.php', [
            [34, 'globalVariable', 'uses_global_var read from global variable $some_global_number.'],
            [34, 'standardOutput', 'uses_global_var writes to standard output (echo).'],
            [36, 'globalVariable', 'uses_global_var wrote to global variable $some_global_number.'],
            [48, 'globalsArray', 'uses_globals_array read from $GLOBALS[\'app_name\'].'],
            [50, 'globalsArray', 'uses_globals_array wrote to $GLOBALS[\'app_name\'].'],
            [61, 'superglobal', 'reads_superglobals read from superglobal $_GET.'],
            [62, 'superglobal', 'reads_superglobals read from superglobal $_POST.'],
            [73, 'superglobal', 'writes_superglobals read from superglobal $_SESSION.'],
            [74, 'superglobal', 'writes_superglobals wrote to superglobal $_SESSION.'],
            [78, 'superglobal', 'writes_superglobals wrote to superglobal $_COOKIE.'],
            [89, 'globalVariable', 'config_and_store_change read from global variable $config.'],
            [93, 'globalsArray', 'config_and_store_change wrote to $GLOBALS[\'store\'].'],
            [93, 'time', 'config_and_store_change reads system time (microtime).'],
            [116, 'globalVariable', 'inc_global_counter read from global variable $some_global_number.'],
            [116, 'globalVariable', 'inc_global_counter wrote to global variable $some_global_number.'],
            [124, 'globalsArray', '{closure} read from $GLOBALS[\'app_name\'].'],
            [125, 'globalsArray', '{closure} wrote to $GLOBALS[\'app_name\'].'],
        ]);
    }

    public function testGoodExamplesReportNothingInStrictMode(): void
    {
        $this->strict = true;

        $this->assertErrors('good-examples.php', []);
    }

    /**
     * Strict mode reports output, file-system, environment, time, random,
     * header, error-log and session functions, but not properties.
     */
    public function testStrictExamplesInStrictMode(): void
    {
        $this->strict = true;

        $this->assertErrors('strict-examples.php', self::STRICT_EXAMPLES_IN_STRICT_MODE);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public function scopeFixtures(): iterable
    {
        yield 'without namespace' => ['scope-global.php', ''];
        yield 'with namespace' => ['scope-namespaced.php', 'App\\Sub\\'];
    }

    /**
     * A named method, an anonymous-class method, a conditionally declared
     * function and closures are all checked, with or without a namespace,
     * under the CLI's names. makesClosures() reports nothing: the closures in
     * its body are checked on their own.
     *
     * @dataProvider scopeFixtures
     */
    public function testChecksEveryFunctionLikeWithTheCliNames(string $fixture, string $namespace): void
    {
        $offset = $namespace === '' ? 0 : 2;

        $this->analyse([self::FIXTURES . $fixture], [
            [$namespace . 'scope_conditional read from global variable $conditional.', 13 + $offset],
            ['class@anonymous::anonymousMethod read from superglobal $_GET.', 20 + $offset],
            [$namespace . 'ScopeNamed::namedMethod read from global variable $named.', 30 + $offset],
            ['{closure} read from superglobal $_POST.', 36 + $offset],
            ['{closure} read from superglobal $_COOKIE.', 38 + $offset],
            ['{closure} read from superglobal $_GET.', 45 + $offset],
        ]);
    }

    /**
     * PHPStan's own anonymous-class names contain the file path, which would
     * make messages (and baselines) differ between machines. The file is
     * analysed twice because PHPStan names anonymous classes in its cached AST
     * during the first analysis.
     *
     * @dataProvider scopeFixtures
     */
    public function testAnonymousClassLabelHasNoPath(string $fixture): void
    {
        $messages = [];
        foreach ([1, 2] as $pass) {
            foreach ($this->gatherAnalyserErrors([self::FIXTURES . $fixture]) as $error) {
                if (str_contains($error->getMessage(), '::anonymousMethod ')) {
                    $messages[] = $pass . ': ' . $error->getMessage();
                }
            }
        }

        self::assertSame(
            [
                '1: class@anonymous::anonymousMethod read from superglobal $_GET.',
                '2: class@anonymous::anonymousMethod read from superglobal $_GET.',
            ],
            $messages,
        );
    }

    /**
     * A closure's inputs belong to the closure, not the enclosing function.
     */
    public function testNestedFunctionLikesAreCheckedSeparately(): void
    {
        $this->analyse([self::FIXTURES . 'closure-global-bleed.php'], [
            ['{closure} read from global variable $x.', 15],
            ['nested_reads_global read from global variable $y.', 28],
            ['class@anonymous::readsGlobal read from global variable $z.', 42],
        ]);
    }

    public function testRepeatedInputIsReportedOnceAtItsFirstLine(): void
    {
        $this->analyse([self::FIXTURES . 'repeated-input.php'], [
            ['repeats_inputs read from superglobal $_GET.', 10],
            ['repeats_inputs wrote to global variable $counter.', 13],
        ]);
    }

    public function testMethodsWithoutABodyReportNothing(): void
    {
        $this->analyse([self::FIXTURES . 'bodyless-methods.php'], []);
    }

    /**
     * Trait methods are reported once under the trait's name, used or not.
     */
    public function testTraitMethodsAreCheckedOnceUnderTheTraitName(): void
    {
        $this->analyse([self::FIXTURES . 'trait-method.php'], [
            ['App\\Traits\\ReadsGlobals::readsSession read from superglobal $_SESSION.', 15],
            ['App\\Traits\\UnusedTrait::readsServer read from superglobal $_SERVER.', 23],
        ]);
    }

    public function testEveryErrorHasAnExplicitnessCategoryIdentifier(): void
    {
        $identifiers = [];
        foreach ($this->gatherAnalyserErrors(self::fixtureFiles()) as $error) {
            $identifiers[(string) $error->getIdentifier()] = true;
        }

        self::assertNotEmpty($identifiers);
        $allowed = array_map(static fn(string $category): string => 'explicitness.' . $category, Category::ALL);
        self::assertSame([], array_diff(array_keys($identifiers), $allowed));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public function everyFixtureInEachMode(): iterable
    {
        foreach (self::fixtureFiles() as $file) {
            $name = substr($file, strlen(self::FIXTURES));
            yield $name . ', default' => [$name, []];
            yield $name . ', strict' => [$name, ['--strict']];
        }
    }

    /**
     * Compares errors as `<line> <identifier> <message>`, sorted, since
     * RuleTestCase::analyse() doesn't check identifiers.
     *
     * @param list<array{int, string, string}> $expected [line, category, message]
     */
    protected function assertErrors(string $fixture, array $expected): void
    {
        $actual = [];
        foreach ($this->gatherAnalyserErrors([self::FIXTURES . $fixture]) as $error) {
            $actual[] = sprintf('%d %s %s', (int) $error->getLine(), (string) $error->getIdentifier(), $error->getMessage());
        }
        sort($actual);

        $wanted = [];
        foreach ($expected as [$line, $category, $message]) {
            $wanted[] = sprintf('%d %s%s %s', $line, ImplicitInputOutputRule::IDENTIFIER_PREFIX, $category, $message);
        }
        sort($wanted);

        self::assertSame($wanted, $actual);
    }

    /**
     * The rule reports exactly what the CLI reports, under the same function
     * names, on every fixture.
     *
     * @dataProvider everyFixtureInEachMode
     *
     * @param list<string> $cliFlags
     */
    public function testReportsWhatTheCliReports(string $fixture, array $cliFlags): void
    {
        $this->strict = in_array('--strict', $cliFlags, true);
        $this->props = in_array('--props', $cliFlags, true);

        $ruleMessages = [];
        foreach ($this->gatherAnalyserErrors([self::FIXTURES . $fixture]) as $error) {
            $ruleMessages[] = $error->getMessage();
        }
        sort($ruleMessages);

        self::assertSame($this->cliMessages($fixture, $cliFlags), $ruleMessages);
    }

    /**
     * Runs the real CLI and turns each table row into the messages the rule
     * should report for it: `<function> <description>.`, sorted.
     *
     * @param list<string> $flags
     *
     * @return list<string>
     */
    protected function cliMessages(string $fixture, array $flags): array
    {
        $command = array_merge(
            [PHP_BINARY, self::ROOT . '/bin/explicitness-checker'],
            $flags,
            [self::FIXTURES . $fixture],
        );
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, self::ROOT);
        self::assertIsResource($process);
        $output = (string) stream_get_contents($pipes[1]);
        $errors = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        self::assertSame('', $errors);

        $messages = [];
        foreach (explode("\n", $output) as $line) {
            $cells = array_map('trim', explode('|', $line));
            if (count($cells) !== 8 || $cells[1] !== basename($fixture)) {
                continue;
            }
            foreach ([$cells[4], $cells[5]] as $descriptions) {
                foreach (array_filter(explode('; ', $descriptions)) as $description) {
                    $messages[] = $cells[3] . ' ' . $description . '.';
                }
            }
        }
        sort($messages);

        return $messages;
    }

    /**
     * @return list<string>
     */
    protected static function fixtureFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::FIXTURES, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }
}
