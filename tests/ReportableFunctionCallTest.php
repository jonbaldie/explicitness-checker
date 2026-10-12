<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests {

    use JonBaldie\ExplicitnessChecker\Detect\BuiltinCatalogueDetector;
    use JonBaldie\ExplicitnessChecker\Detect\ReportableFunctionCall;
    use JonBaldie\ExplicitnessChecker\Tests\Support\SyntheticByReferenceParameters;
    use PhpParser\Node;
    use PhpParser\Node\Stmt;
    use PhpParser\NodeTraverser;
    use PhpParser\NodeVisitor\NameResolver;
    use PhpParser\ParserFactory;
    use PHPUnit\Framework\TestCase;

    class ReportableFunctionCallTest extends TestCase
    {
        /** @dataProvider catalogueArm */
        public function testCatalogueArmWhenReflectionClaimsNothing(string $source, ?string $expected): void
        {
            $reportable = new ReportableFunctionCall(new SyntheticByReferenceParameters([]));

            self::assertSame($expected, $reportable->spelling($this->expression($source)));
        }

        public static function catalogueArm(): array
        {
            return [
                ['time()', null],
                ['TIME()', null],
                ['date()', null],
                ['session_id()', null],
                ['error_reporting()', null],
                ['ignore_user_abort()', null],
                ['http_response_code()', null],
                ['fopen()', null],
                ['print_r()', null],
                ['var_export()', null],
                ['\\exit()', null],
                ['\\die()', null],
                ['mysqli_not_a_real_function()', null],
                ['ob_get_level()', null],
                ['strlen()', 'strlen'],
                ['auth()', 'auth'],
                ['Auth()', 'Auth'],
                ['collect()', 'collect'],
                ['App\\time()', 'App\\time'],
            ];
        }

        /** @dataProvider runningPhp */
        public function testRunningPhpExcludesInternalsAndReportsUserFunctions(string $source, ?string $expected): void
        {
            $reportable = new ReportableFunctionCall();

            self::assertSame($expected, $reportable->spelling($this->expression($source)));
        }

        public static function runningPhp(): array
        {
            return [
                ['strlen()', null],
                ['TIME()', null],
                ['phpversion()', null],
                ['session_start()', null],
                ['current_tenant()', 'current_tenant'],
                ['explicitness_checker_loaded_user_function()', 'explicitness_checker_loaded_user_function'],
                ['ob_custom()', null],
            ];
        }

        /**
         * Bare exit and die are constructs, not calls.
         *
         * @dataProvider quietShapes
         */
        public function testStaysQuietUnlessTheCallIsNamedAndEmpty(string $source): void
        {
            $reportable = new ReportableFunctionCall(new SyntheticByReferenceParameters([]));

            self::assertNull($reportable->spelling($this->expression($source)));
        }

        public static function quietShapes(): array
        {
            return [
                ['now($tz)'],
                ['now(...)'],
                ['now(...$args)'],
                ['$callback()'],
                ["'auth'()"],
                ['(now(...))()'],
                ["call_user_func('auth')"],
                ['exit()'],
                ['die()'],
                ['1'],
            ];
        }

        /** @dataProvider emptyCallClaims */
        public function testWouldReportEmptyCallWhenTheCatalogueHasEntries(string $name, bool $claimed): void
        {
            self::assertSame($claimed, (new BuiltinCatalogueDetector())->wouldReportEmptyCall($name));
        }

        public static function emptyCallClaims(): array
        {
            return [
                ['time', true],
                ['TIME', true],
                ['date', true],
                ['session_id', true],
                ['error_reporting', true],
                ['ignore_user_abort', true],
                ['http_response_code', true],
                ['fopen', true],
                ['print_r', true],
                ['var_export', true],
                ['exit', true],
                ['die', true],
                ['mysqli_not_a_real_function', true],
                ['ob_get_level', true],
                ['strlen', false],
                ['auth', false],
                ['collect', false],
                ['App\\time', false],
                ['current_tenant', false],
            ];
        }

        protected function expression(string $code): Node
        {
            $statements = (new ParserFactory())->createForNewestSupportedVersion()->parse('<?php ' . $code . ';') ?? [];
            $resolved = (new NodeTraverser(new NameResolver()))->traverse($statements);
            $statement = $resolved[0] ?? null;
            self::assertInstanceOf(Stmt\Expression::class, $statement);

            return $statement->expr;
        }
    }
}

namespace {
    function explicitness_checker_loaded_user_function(): int
    {
        return 1;
    }

    function ob_custom(): void
    {
    }
}
