<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests\PHPStan;

use JonBaldie\ExplicitnessChecker\Analyser;
use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\Mode;
use JonBaldie\ExplicitnessChecker\PHPStan\ImplicitInputOutputRule;
use JonBaldie\ExplicitnessChecker\Tests\Support\CheckedFile;
use JonBaldie\ExplicitnessChecker\Tests\Support\Process;
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
    protected const FIXTURES = Process::ROOT . '/test-fixtures/';

    protected const FOPEN_FIXTURE = Process::ROOT . '/tests/Fixtures/fopen-modes.php';

    protected const FIRST_CLASS_CALLABLES_FIXTURE = Process::ROOT . '/tests/Fixtures/first-class-callables.php';

    protected const EXIT_FIXTURE = Process::ROOT . '/tests/Fixtures/exit-forms.php';

    protected const CASE_INSENSITIVE_FIXTURE = Process::ROOT . '/tests/Fixtures/case-insensitive-functions.php';

    protected const REFERENCE_ALIAS_FIXTURE = Process::ROOT . '/tests/Fixtures/reference-global-alias.php';

    protected const REFERENCE_TARGET_FIXTURE = Process::ROOT . '/tests/Fixtures/reference-global-target.php';

    protected const PROPERTY_CHAIN_FIXTURE = Process::ROOT . '/tests/Fixtures/property-chain.php';

    protected const DYNAMIC_STATIC_PROPERTY_CLASS_FIXTURE = Process::ROOT . '/test-fixtures/read-write-context/static-class.php';

    protected const NESTED_ANONYMOUS_FIXTURE = Process::ROOT . '/tests/Fixtures/nested-anonymous-classes.php';

    protected const UNSET_REFERENCE_BINDING_FIXTURE = Process::ROOT . '/tests/Fixtures/unset-reference-binding.php';

    protected const UNSET_SHARED_LOCATION_FIXTURE = Process::ROOT . '/tests/Fixtures/unset-shared-location.php';

    protected const STATIC_CALL_FIXTURE = Process::ROOT . '/tests/Fixtures/static-call.php';

    protected const DYNAMIC_STATIC_CALL_FIXTURE = Process::ROOT . '/tests/Fixtures/dynamic-static-call.php';

    protected const DYNAMIC_GLOBAL_FIXTURE = Process::ROOT . '/tests/Fixtures/dynamic-global.php';

    protected const DYNAMIC_THIS_PROPERTY_FIXTURE = Process::ROOT . '/tests/Fixtures/dynamic-this-property.php';

    protected const NULLSAFE_THIS_PROPERTY_FIXTURE = Process::ROOT . '/tests/Fixtures/nullsafe-this-property.php';

    protected const KEYED_DESTRUCTURING_FIXTURE = Process::ROOT . '/tests/Fixtures/keyed-destructuring.php';

    protected const RUNTIME_CONFIG_FIXTURE = Process::ROOT . '/tests/Fixtures/runtime-config.php';

    protected const STRICT_CATALOGUE_ADDITIONS_FIXTURE = Process::ROOT . '/tests/Fixtures/strict-catalogue-additions.php';

    /**
     * What default mode reports on bad-examples.php, with identifiers.
     */
    protected const BAD_EXAMPLES_IN_DEFAULT_MODE = [
        [34, 'globalVariable', 'uses_global_var read from global variable $some_global_number.'],
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
        [106, 'globalVariable', 'variable_variable_global_read read from global variable $....'],
        [116, 'globalVariable', 'inc_global_counter read from global variable $some_global_number.'],
        [116, 'globalVariable', 'inc_global_counter wrote to global variable $some_global_number.'],
        [124, 'globalsArray', '{closure} read from $GLOBALS[\'app_name\'].'],
        [125, 'globalsArray', '{closure} wrote to $GLOBALS[\'app_name\'].'],
    ];

    /**
     * What strict mode reports on bad-examples.php: the default-mode errors
     * plus echo and microtime.
     */
    protected const BAD_EXAMPLES_IN_STRICT_MODE = [
        ...self::BAD_EXAMPLES_IN_DEFAULT_MODE,
        [34, 'standardOutput', 'uses_global_var writes to standard output (echo).'],
        [93, 'time', 'config_and_store_change reads system time (microtime).'],
    ];

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

    /**
     * What default mode reports on strict-examples.php: static properties are
     * shared state, so they no longer wait for props mode (#72).
     */
    protected const STRICT_EXAMPLES_IN_DEFAULT_MODE = [
        [61, 'staticProperty', 'AppAnalytics::recordPageView read from static property self::$pageViews.'],
        [61, 'staticProperty', 'AppAnalytics::recordPageView wrote to static property self::$pageViews.'],
        [70, 'staticProperty', 'AppAnalytics::logEvent read from static property self::$pageViews.'],
        [71, 'staticProperty', 'AppAnalytics::logEvent wrote to static property self::$events.'],
    ];

    /**
     * What props mode adds on strict-examples.php.
     */
    protected const STRICT_EXAMPLES_IN_PROPS_MODE = [
        [24, 'objectProperty', 'UserSession::__construct wrote to object property $this->username.'],
        [33, 'objectProperty', 'UserSession::greet read from object property $this->username.'],
        [42, 'objectProperty', 'UserSession::incrementLoginCount read from object property $this->loginCount.'],
        [42, 'objectProperty', 'UserSession::incrementLoginCount wrote to object property $this->loginCount.'],
    ];

    protected bool $strict = false;

    protected bool $props = false;

    protected function getRule(): Rule
    {
        return new ImplicitInputOutputRule(new Analyser(), new FunctionLikeFinder(), new Mode($this->strict, $this->props));
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
            ['variable_variable_global_read read from global variable $....', 106],
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

    public function testStrictExamplesReportOnlyStaticPropertiesInDefaultMode(): void
    {
        $this->assertErrors('strict-examples.php', self::STRICT_EXAMPLES_IN_DEFAULT_MODE);
    }

    public function testBadExamplesInStrictMode(): void
    {
        $this->strict = true;

        $this->assertErrors('bad-examples.php', self::BAD_EXAMPLES_IN_STRICT_MODE);
    }

    public function testGoodExamplesReportNothingInStrictMode(): void
    {
        $this->strict = true;

        $this->assertErrors('good-examples.php', []);
    }

    /**
     * Strict mode reports output, file-system, environment, time, random,
     * header, error-log and session functions on top of the default findings,
     * but not object properties.
     */
    public function testStrictExamplesInStrictMode(): void
    {
        $this->strict = true;

        $this->assertErrors(
            'strict-examples.php',
            array_merge(self::STRICT_EXAMPLES_IN_DEFAULT_MODE, self::STRICT_EXAMPLES_IN_STRICT_MODE),
        );
    }

    public function testFopenModesInStrictMode(): void
    {
        $this->strict = true;

        $this->assertErrorsAtPath(self::FOPEN_FIXTURE, [
            [5, 'file', 'fopen_read reads from file (fopen).'],
            [10, 'file', 'fopen_write writes to file (fopen).'],
            [15, 'file', 'fopen_append writes to file (fopen).'],
            [20, 'file', 'fopen_create writes to file (fopen).'],
            [25, 'file', 'fopen_exclusive writes to file (fopen).'],
            [30, 'file', 'fopen_read_write reads from file (fopen).'],
            [30, 'file', 'fopen_read_write writes to file (fopen).'],
            [35, 'file', 'fopen_dynamic reads from file (fopen).'],
            [40, 'file', 'fopen_named_mode_first writes to file (fopen).'],
        ]);
    }

    public function testFirstClassCallablesAreNotReportedInStrictMode(): void
    {
        $this->strict = true;

        $this->assertErrorsAtPath(self::FIRST_CLASS_CALLABLES_FIXTURE, []);
    }

    /**
     * bad-examples.php touches no properties, so props mode adds nothing.
     */
    public function testBadExamplesInPropsMode(): void
    {
        $this->props = true;

        $this->assertErrors('bad-examples.php', self::BAD_EXAMPLES_IN_DEFAULT_MODE);
    }

    public function testGoodExamplesReportNothingInPropsMode(): void
    {
        $this->props = true;

        $this->assertErrors('good-examples.php', []);
    }

    public function testStrictExamplesInPropsMode(): void
    {
        $this->props = true;

        $this->assertErrors(
            'strict-examples.php',
            array_merge(self::STRICT_EXAMPLES_IN_DEFAULT_MODE, self::STRICT_EXAMPLES_IN_PROPS_MODE),
        );
    }

    public function testBadExamplesInStrictAndPropsMode(): void
    {
        $this->strict = true;
        $this->props = true;

        $this->assertErrors('bad-examples.php', self::BAD_EXAMPLES_IN_STRICT_MODE);
    }

    public function testGoodExamplesReportNothingInStrictAndPropsMode(): void
    {
        $this->strict = true;
        $this->props = true;

        $this->assertErrors('good-examples.php', []);
    }

    public function testStrictExamplesInStrictAndPropsMode(): void
    {
        $this->strict = true;
        $this->props = true;

        $this->assertErrors(
            'strict-examples.php',
            array_merge(
                self::STRICT_EXAMPLES_IN_DEFAULT_MODE,
                self::STRICT_EXAMPLES_IN_STRICT_MODE,
                self::STRICT_EXAMPLES_IN_PROPS_MODE,
            ),
        );
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

    /**
     * #136: the PHPStan extension does not report unset() of a local reference
     * binding, and still reports unset() of the shared location.
     */
    public function testUnsetOfReferenceBindingIsNotAWrite(): void
    {
        $this->analyse([self::UNSET_REFERENCE_BINDING_FIXTURE], []);
    }

    /**
     * #136: unsetting an element, a property, a superglobal, or a `$GLOBALS`
     * entry is still a write, including in a mixed unset.
     */
    public function testUnsetOfSharedLocationIsStillAWrite(): void
    {
        $this->props = true;
        $this->assertErrorsAtPath(self::UNSET_SHARED_LOCATION_FIXTURE, [
            [8, 'argumentMutation', 'unset_element wrote to argument $cart.'],
            [13, 'argumentMutation', 'unset_property wrote to argument $product.'],
            [18, 'superglobal', 'unset_superglobal_element wrote to superglobal $_GET.'],
            [19, 'superglobal', 'unset_superglobal_element wrote to superglobal $_SESSION.'],
            [24, 'superglobal', 'unset_superglobal wrote to superglobal $_GET.'],
            [25, 'superglobal', 'unset_superglobal wrote to superglobal $_SESSION.'],
            [30, 'globalsArray', 'unset_globals_entry wrote to $GLOBALS[\'cart\'].'],
            [35, 'argumentMutation', 'unset_mixed wrote to argument $cart.'],
            [41, 'superglobal', 'unset_mixed_global wrote to superglobal $_GET.'],
            [48, 'objectProperty', 'Cached::clear wrote to object property $this->cached.'],
            [55, 'globalsArray', 'unset_alias_element wrote to $GLOBALS[\'cart\'].'],
            [60, 'superglobal', 'unset_globals_symbol wrote to superglobal $GLOBALS.'],
        ]);
    }

    /**
     * #45: the PHPStan rule follows global channels through reference aliases,
     * including rebinds and read/write operators.
     */
    public function testReferenceAliasesPreserveGlobalChannels(): void
    {
        $this->assertErrorsAtPath(self::REFERENCE_ALIAS_FIXTURE, [
            [9, 'globalsArray', 'writeThroughAlias wrote to $GLOBALS[\'counter\'].'],
            [15, 'globalsArray', 'readWriteThroughAlias read from $GLOBALS[\'total\'].'],
            [15, 'globalsArray', 'readWriteThroughAlias wrote to $GLOBALS[\'total\'].'],
            [21, 'globalsArray', 'incrementAndDecrementThroughAliases read from $GLOBALS[\'up\'].'],
            [21, 'globalsArray', 'incrementAndDecrementThroughAliases wrote to $GLOBALS[\'up\'].'],
            [23, 'globalsArray', 'incrementAndDecrementThroughAliases read from $GLOBALS[\'down\'].'],
            [23, 'globalsArray', 'incrementAndDecrementThroughAliases wrote to $GLOBALS[\'down\'].'],
            [37, 'globalsArray', 'rebindAlias wrote to $GLOBALS[\'second\'].'],
            [43, 'globalsArray', 'dynamicGlobalKey wrote to $GLOBALS[$key].'],
            [55, 'argumentMutation', 'parameterReference wrote to argument $value.'],
            [60, 'superglobal', 'nonGlobalsReference read from superglobal $_SESSION.'],
            [60, 'superglobal', 'nonGlobalsReference wrote to superglobal $_SESSION.'],
        ]);
    }

    /**
     * #92: assigning $GLOBALS entries by reference to non-variable targets
     * reports writes to those targets and read+write to the global entry.
     */
    public function testReferenceAssignmentOfGlobalsToNonVariables(): void
    {
        $this->props = true;

        $this->assertErrorsAtPath(self::REFERENCE_TARGET_FIXTURE, [
            [14, 'globalsArray', 'RefBug::assignThis read from $GLOBALS[\'counter\'].'],
            [14, 'objectProperty', 'RefBug::assignThis wrote to object property $this->ref.'],
            [14, 'globalsArray', 'RefBug::assignThis wrote to $GLOBALS[\'counter\'].'],
            [19, 'globalsArray', 'RefBug::assignStatic read from $GLOBALS[\'counter\'].'],
            [19, 'staticProperty', 'RefBug::assignStatic wrote to static property self::$staticRef.'],
            [19, 'globalsArray', 'RefBug::assignStatic wrote to $GLOBALS[\'counter\'].'],
            [25, 'globalsArray', 'mutateParamRef read from $GLOBALS[\'counter\'].'],
            [25, 'argumentMutation', 'mutateParamRef wrote to argument $param.'],
            [25, 'globalsArray', 'mutateParamRef wrote to $GLOBALS[\'counter\'].'],
            [30, 'globalsArray', 'mutateParamArrayRef read from $GLOBALS[\'counter\'].'],
            [30, 'argumentMutation', 'mutateParamArrayRef wrote to argument $arr.'],
            [30, 'globalsArray', 'mutateParamArrayRef wrote to $GLOBALS[\'counter\'].'],
        ]);
    }

    /**
     * #81: the PHPStan extension reports dynamic global reads and writes from
     * the same analyser results as the CLI.
     */
    public function testDynamicGlobalDeclarationsReportVariableVariableAccesses(): void
    {
        $this->assertErrorsAtPath(self::DYNAMIC_GLOBAL_FIXTURE, [
            [6, 'globalVariable', 'writes_to_dynamic_global wrote to global variable $....'],
            [12, 'globalVariable', 'reads_from_dynamic_global read from global variable $....'],
            [18, 'globalVariable', 'writes_to_dynamic_global_with_expression wrote to global variable $....'],
            [25, 'globalVariable', 'reads_name_expression_from_global read from global variable $....'],
            [25, 'globalVariable', 'reads_name_expression_from_global read from global variable $name.'],
        ]);
    }

    /**
     * #82: the PHPStan rule reports a write through a property chain as a
     * read and a write of the chain's base, like the CLI.
     */
    public function testWriteThroughPropertyChainReadsItsBase(): void
    {
        $this->props = true;

        $this->assertErrorsAtPath(self::PROPERTY_CHAIN_FIXTURE, [
            [19, 'objectProperty', 'Node::unlink read from object property $this->next.'],
            [19, 'objectProperty', 'Node::unlink wrote to object property $this->next.'],
            [24, 'objectProperty', 'Node::append read from object property $this->next.'],
            [24, 'objectProperty', 'Node::append wrote to object property $this->next.'],
            [29, 'staticProperty', 'Node::resetHead read from static property self::$head.'],
            [29, 'staticProperty', 'Node::resetHead wrote to static property self::$head.'],
            [34, 'objectProperty', 'Node::direct wrote to object property $this->count.'],
            [39, 'objectProperty', 'Node::readChain read from object property $this->next.'],
            [46, 'globalVariable', 'write_through_global read from global variable $config.'],
            [46, 'globalVariable', 'write_through_global wrote to global variable $config.'],
        ]);
    }

    /**
     * #106: a static-property write reads the dynamic class expression; reads
     * of dynamic static properties remain unchanged.
     */
    public function testDynamicClassExpressionOnStaticPropertyWriteIsRead(): void
    {
        $this->props = true;

        $this->assertErrorsAtPath(self::DYNAMIC_STATIC_PROPERTY_CLASS_FIXTURE, [
            [10, 'staticProperty', 'DynamicClassProbe::writeThroughParam wrote to static property ...::$value.'],
            [16, 'globalVariable', 'DynamicClassProbe::writeThroughGlobal read from global variable $className.'],
            [16, 'staticProperty', 'DynamicClassProbe::writeThroughGlobal wrote to static property ...::$value.'],
            [21, 'objectProperty', 'DynamicClassProbe::writeThroughProperty read from object property $this->className.'],
            [21, 'staticProperty', 'DynamicClassProbe::writeThroughProperty wrote to static property ...::$value.'],
            [26, 'globalsArray', "DynamicClassProbe::writeThroughGlobals read from \$GLOBALS['className']."],
            [26, 'staticProperty', 'DynamicClassProbe::writeThroughGlobals wrote to static property ...::$value.'],
            [31, 'staticProperty', 'DynamicClassProbe::readThroughParam read from static property ...::$value.'],
        ]);
    }

    /**
     * #94: the PHPStan rule reports `$this` properties with dynamic names
     * under props, like the CLI.
     */
    public function testDynamicThisPropertyNamesUnderProps(): void
    {
        $this->props = true;

        $this->assertErrorsAtPath(self::DYNAMIC_THIS_PROPERTY_FIXTURE, [
            [7, 'objectProperty', 'DynamicProps::readDynamic read from object property $this->....'],
            [12, 'objectProperty', 'DynamicProps::writeDynamic wrote to object property $this->....'],
            [17, 'objectProperty', 'DynamicProps::readBraced read from object property $this->....'],
            [22, 'objectProperty', 'DynamicProps::readLiteral read from object property $this->literal.'],
        ]);
    }

    /**
     * #93: the PHPStan rule reports nullsafe property fetches on `$this` under
     * props, like the CLI.
     */
    public function testNullsafeThisPropertyUnderProps(): void
    {
        $this->props = true;

        $this->assertErrorsAtPath(self::NULLSAFE_THIS_PROPERTY_FIXTURE, [
            [7, 'objectProperty', 'NullsafeProps::readLiteral read from object property $this->name.'],
            [12, 'objectProperty', 'NullsafeProps::readDynamic read from object property $this->....'],
            [17, 'objectProperty', 'NullsafeProps::readBraced read from object property $this->....'],
        ]);
    }

    /**
     * #105: the PHPStan rule reads the key of a keyed destructuring item and
     * writes only its value, like the CLI.
     */
    public function testKeyedDestructuringKeyIsRead(): void
    {
        $this->props = true;

        $this->assertErrorsAtPath(self::KEYED_DESTRUCTURING_FIXTURE, [
            [16, 'objectProperty', 'KeyedDestructureProbe::readKeyThroughProp read from object property $this->key.'],
            [26, 'objectProperty', 'KeyedDestructureProbe::writeValueToProp wrote to object property $this->value.'],
            [31, 'objectProperty', 'KeyedDestructureProbe::readKeyInNestedList read from object property $this->key.'],
            [38, 'globalVariable', 'read_global_key read from global variable $key.'],
            [43, 'globalsArray', "read_globals_array_key read from \$GLOBALS['key']."],
            [48, 'staticProperty', 'read_static_key read from static property KeyedDestructureProbe::$staticKey.'],
            [54, 'globalVariable', 'read_foreach_key read from global variable $key.'],
            [61, 'globalVariable', 'write_global_value wrote to global variable $out.'],
        ]);
    }

    /**
     * Class names are resolved against the namespace and `use` imports, so
     * `Registry::$items` and `\App\Sub\Registry::$items` are one input. The
     * CLI must report the same (see testReportsWhatTheCliReports).
     */
    public function testStaticPropertyClassNamesAreFullyQualified(): void
    {
        $this->props = true;

        $this->assertErrors('namespaced-static-property.php', [
            [28, 'staticProperty', 'App\\Sub\\Consumer::reads read from static property App\\Sub\\Registry::$items.'],
            [28, 'staticProperty', 'App\\Sub\\Consumer::reads read from static property Other\\Thing::$shared.'],
            [28, 'staticProperty', 'App\\Sub\\Consumer::reads read from static property Other\\Config::$values.'],
            [33, 'staticProperty', 'App\\Sub\\Consumer::writes wrote to static property App\\Sub\\Registry::$count.'],
            [34, 'staticProperty', 'App\\Sub\\Consumer::writes wrote to static property App\\Sub\\Nested\\Store::$cache.'],
            [35, 'staticProperty', 'App\\Sub\\Consumer::writes read from static property self::$calls.'],
            [35, 'staticProperty', 'App\\Sub\\Consumer::writes wrote to static property self::$calls.'],
            [36, 'staticProperty', 'App\\Sub\\Consumer::writes wrote to static property static::$calls.'],
        ]);
    }

    /**
     * `\exit()` and `\die()` parse as function calls, not language constructs.
     */
    public function testFullyQualifiedExitAndDieUseTheirArgumentSemanticsInStrictMode(): void
    {
        $this->strict = true;

        $this->assertErrors('fully-qualified-exit.php', [
            [11, 'standardOutput', 'quits_as_a_function terminates the program (exit).'],
            [16, 'standardOutput', 'dies_as_a_function writes to standard output (die).'],
        ]);
    }

    public function testExitAndDieArgumentSemanticsInStrictMode(): void
    {
        $this->strict = true;

        $this->assertErrorsAtPath(self::EXIT_FIXTURE, [
            [5, 'standardOutput', 'exit_with_status terminates the program (exit).'],
            [10, 'standardOutput', 'exit_without_status terminates the program (exit).'],
            [15, 'standardOutput', 'exit_with_message writes to standard output (exit).'],
            [20, 'standardOutput', 'exit_with_dynamic_value terminates the program (exit).'],
            [25, 'standardOutput', 'die_with_status terminates the program (die).'],
            [30, 'standardOutput', 'die_without_status terminates the program (die).'],
            [35, 'standardOutput', 'die_with_message writes to standard output (die).'],
            [40, 'standardOutput', 'die_with_dynamic_value terminates the program (die).'],
            [45, 'standardOutput', 'qualified_exit_with_status terminates the program (exit).'],
            [50, 'standardOutput', 'qualified_exit_with_message writes to standard output (exit).'],
            [55, 'standardOutput', 'qualified_exit_with_dynamic_value terminates the program (exit).'],
            [60, 'standardOutput', 'qualified_die_with_status terminates the program (die).'],
            [65, 'standardOutput', 'qualified_die_with_message writes to standard output (die).'],
            [70, 'standardOutput', 'qualified_die_with_dynamic_value terminates the program (die).'],
            [75, 'standardOutput', 'mixed_case_die_with_status terminates the program (die).'],
            [80, 'standardOutput', 'uppercase_die_with_message writes to standard output (die).'],
            [85, 'standardOutput', 'mixed_case_exit_with_dynamic_value terminates the program (exit).'],
        ]);
    }

    /**
     * #46: the rule follows PHP's case-insensitive function-name semantics, so
     * catalogue calls written in any casing are reported, keeping the CLI's
     * description spelling.
     */
    public function testCatalogueCallsInAnyCasingInStrictMode(): void
    {
        $this->strict = true;

        $this->assertErrorsAtPath(self::CASE_INSENSITIVE_FIXTURE, [
            [5, 'standardOutput', 'writes_uppercase writes to standard output (VAR_DUMP).'],
            [10, 'time', 'reads_time_mixed_case reads system time (Time).'],
            [11, 'standardOutput', 'reads_time_mixed_case writes to standard output (echo).'],
            [16, 'random', 'reads_random_mixed_case reads from random number generator (Rand).'],
            [17, 'standardOutput', 'reads_random_mixed_case writes to standard output (echo).'],
            [22, 'file', 'reads_file_mixed_case reads from file (FOPEN).'],
            [23, 'standardOutput', 'reads_file_mixed_case writes to standard output (var_dump).'],
        ]);
    }

    /**
     * #83: `ini_alter` and the `restore_*_handler` inverses are runtime-config
     * writes like `ini_set` and the `set_*_handler` functions, in strict mode
     * only.
     */
    public function testRuntimeConfigAliasesAndInversesInStrictMode(): void
    {
        $this->strict = true;

        $this->assertErrorsAtPath(self::RUNTIME_CONFIG_FIXTURE, [
            [4, 'runtimeConfig', 'use_utc writes runtime configuration (date_default_timezone_set).'],
            [8, 'runtimeConfig', 'alter_ini writes runtime configuration (ini_alter).'],
            [12, 'runtimeConfig', 'restore_handlers writes runtime configuration (restore_error_handler).'],
            [13, 'runtimeConfig', 'restore_handlers writes runtime configuration (RESTORE_EXCEPTION_HANDLER).'],
        ]);
    }

    public function testRuntimeConfigIsNotReportedInDefaultMode(): void
    {
        $this->assertErrorsAtPath(self::RUNTIME_CONFIG_FIXTURE, []);
    }

    /**
     * #84: the network, database, request-input and runtime-config built-ins
     * added to the strict catalogue, under their categories' identifiers.
     */
    public function testStrictCatalogueAdditionsInStrictMode(): void
    {
        $this->strict = true;

        $this->assertErrorsAtPath(self::STRICT_CATALOGUE_ADDITIONS_FIXTURE, [
            [4, 'superglobal', 'parse_options reads from superglobals (getopt).'],
            [8, 'network', 'fetch_headers reads from network (get_headers).'],
            [8, 'network', 'fetch_headers writes to network (get_headers).'],
            [12, 'network', 'connect_ftp reads from network (ftp_connect).'],
            [12, 'network', 'connect_ftp writes to network (ftp_connect).'],
            [16, 'database', 'run_odbc reads from database (odbc_exec).'],
            [16, 'database', 'run_odbc writes to database (odbc_exec).'],
            [20, 'network', 'open_socket reads from network (socket_create).'],
            [20, 'network', 'open_socket writes to network (socket_create).'],
            [24, 'runtimeConfig', 'extend_time_limit writes runtime configuration (set_time_limit).'],
            [28, 'file', 'prompt reads from file (readline).'],
            [32, 'httpHeaders', 'request_headers reads HTTP headers (getallheaders).'],
            [36, 'network', 'reverse_lookup reads from network (gethostbyaddr).'],
            [40, 'network', 'has_mail_exchanger reads from network (checkdnsrr).'],
            [44, 'runtimeConfig', 'keep_running writes runtime configuration (ignore_user_abort).'],
        ]);
    }

    public function testStrictCatalogueAdditionsAreNotReportedInDefaultMode(): void
    {
        $this->assertErrorsAtPath(self::STRICT_CATALOGUE_ADDITIONS_FIXTURE, []);
    }

    public function testArgumentlessStaticCallsInDefaultMode(): void
    {
        $this->assertErrorsAtPath(self::STATIC_CALL_FIXTURE, [
            [4, 'staticCall', 'accesses_static_helper read from static method SomeClass::staticMethod().'],
        ]);
    }

    public function testDynamicStaticCallsInDefaultMode(): void
    {
        $this->assertErrorsAtPath(self::DYNAMIC_STATIC_CALL_FIXTURE, [
            [5, 'staticCall', 'dynamic read from static method ...::now().'],
            [10, 'staticCall', 'parenthesized read from static method ...::now().'],
            [17, 'staticCall', 'dynamic_method read from static method Clock::...().'],
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

    /**
     * #27: a property hook is named after its class, property and hook kind,
     * under the CLI's names, and `{closure}` is left to the arrow function in
     * one of the hooks.
     */
    public function testPropertyHooksAreNamedAfterTheirPropertyAndHookKind(): void
    {
        $this->props = true;

        $this->assertErrors('property-hooks.php', [
            [15, 'objectProperty', 'App\\Sub\\Temperature::$celsius::get read from object property $this->celsius.'],
            [17, 'objectProperty', 'App\\Sub\\Temperature::$celsius::set wrote to object property $this->celsius.'],
            [22, 'superglobal', 'App\\Sub\\Temperature::$source::get read from superglobal $_GET.'],
            [26, 'objectProperty', 'App\\Sub\\Temperature::$label::get read from object property $this->label.'],
            [34, 'superglobal', '{closure} read from superglobal $_POST.'],
            [36, 'superglobal', 'class@anonymous::$reading::get read from superglobal $_SERVER.'],
        ]);
    }

    /**
     * #47: nested anonymous classes retain their named enclosing class for
     * methods and property hooks.
     */
    public function testNestedAnonymousClassesUseTheirNamedEnclosingClass(): void
    {
        $this->assertErrorsAtPath(self::NESTED_ANONYMOUS_FIXTURE, [
            [12, 'superglobal', 'App\\ServiceA::class@anonymous::send read from superglobal $_GET.'],
            [16, 'superglobal', 'App\\ServiceA::class@anonymous::$value::get read from superglobal $_GET.'],
            [29, 'superglobal', 'App\\ServiceB::class@anonymous::send read from superglobal $_GET.'],
            [33, 'superglobal', 'App\\ServiceB::class@anonymous::$value::get read from superglobal $_GET.'],
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
            yield $name . ', props' => [$name, ['--props']];
            yield $name . ', strict and props' => [$name, ['--strict', '--props']];
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
        $this->assertErrorsAtPath(self::FIXTURES . $fixture, $expected);
    }

    /**
     * Compares errors as `<line> <identifier> <message>`, sorted, since
     * RuleTestCase::analyse() doesn't check identifiers.
     *
     * @param list<array{int, string, string}> $expected [line, category, message]
     */
    protected function assertErrorsAtPath(string $path, array $expected): void
    {
        $actual = [];
        foreach ($this->gatherAnalyserErrors([$path]) as $error) {
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
     * Checks the fixture as the CLI does and turns each report row into the
     * messages the rule should report for it: `<function> <description>.`, sorted.
     *
     * @param list<string> $flags
     *
     * @return list<string>
     */
    protected function cliMessages(string $fixture, array $flags): array
    {
        $messages = [];
        foreach (CheckedFile::violations(self::FIXTURES . $fixture, $flags) as $violation) {
            foreach (array_merge($violation->getInputs(), $violation->getOutputs()) as $description) {
                $messages[] = $violation->getFunction() . ' ' . $description . '.';
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
