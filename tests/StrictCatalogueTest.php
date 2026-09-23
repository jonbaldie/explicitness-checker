<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Category;
use JonBaldie\ExplicitnessChecker\Finding;
use JonBaldie\ExplicitnessChecker\Mode;
use JonBaldie\ExplicitnessChecker\SourceChecker;
use PHPUnit\Framework\TestCase;

/**
 * Strict mode's catalogue of built-ins and constructs (#72). Each statement is
 * checked on its own inside a function whose parameters are $a and $b, and
 * its findings come back as [description, category] pairs: inputs, then
 * outputs.
 */
class StrictCatalogueTest extends TestCase
{
    public function testReportsNetworkAccess(): void
    {
        $this->assertFindings([
            'curl_exec($a)' => [
                [['reads from network (curl_exec)', Category::NETWORK]],
                [['writes to network (curl_exec)', Category::NETWORK]],
            ],
            'curl_multi_exec($a, $b)' => [
                [['reads from network (curl_multi_exec)', Category::NETWORK]],
                [['writes to network (curl_multi_exec)', Category::NETWORK]],
            ],
            'fsockopen($a)' => [
                [['reads from network (fsockopen)', Category::NETWORK]],
                [['writes to network (fsockopen)', Category::NETWORK]],
            ],
            'pfsockopen($a)' => [
                [['reads from network (pfsockopen)', Category::NETWORK]],
                [['writes to network (pfsockopen)', Category::NETWORK]],
            ],
            'stream_socket_client($a)' => [
                [['reads from network (stream_socket_client)', Category::NETWORK]],
                [['writes to network (stream_socket_client)', Category::NETWORK]],
            ],
            'gethostbyname($a)' => [[['reads from network (gethostbyname)', Category::NETWORK]], []],
            'gethostbynamel($a)' => [[['reads from network (gethostbynamel)', Category::NETWORK]], []],
            'dns_get_record($a)' => [[['reads from network (dns_get_record)', Category::NETWORK]], []],
        ]);
    }

    /**
     * Every mysqli_* and pg_* function, however it's cased. Only the prefix
     * counts: a name that merely contains it, or the removed mysql_ API, is
     * not reported.
     */
    public function testReportsProceduralDatabaseAccessByPrefix(): void
    {
        $this->assertFindings([
            'mysqli_query($a, $b)' => [
                [['reads from database (mysqli_query)', Category::DATABASE]],
                [['writes to database (mysqli_query)', Category::DATABASE]],
            ],
            'MySQLi_Connect()' => [
                [['reads from database (MySQLi_Connect)', Category::DATABASE]],
                [['writes to database (MySQLi_Connect)', Category::DATABASE]],
            ],
            'pg_query($a)' => [
                [['reads from database (pg_query)', Category::DATABASE]],
                [['writes to database (pg_query)', Category::DATABASE]],
            ],
            'my_mysqli_query($a)' => [[], []],
            'mysql_query($a)' => [[], []],
            'pgsql($a)' => [[], []],
        ]);
    }

    /**
     * A backtick expression calls shell_exec, so it's described as one.
     */
    public function testReportsExternalProcesses(): void
    {
        $this->assertFindings([
            'exec($a)' => [
                [['reads from external process (exec)', Category::PROCESS]],
                [['runs external process (exec)', Category::PROCESS]],
            ],
            'shell_exec($a)' => [
                [['reads from external process (shell_exec)', Category::PROCESS]],
                [['runs external process (shell_exec)', Category::PROCESS]],
            ],
            'system($a)' => [
                [['reads from external process (system)', Category::PROCESS]],
                [['runs external process (system)', Category::PROCESS]],
            ],
            'passthru($a)' => [
                [['reads from external process (passthru)', Category::PROCESS]],
                [['runs external process (passthru)', Category::PROCESS]],
            ],
            'proc_open($a, $b, $pipes)' => [
                [['reads from external process (proc_open)', Category::PROCESS]],
                [['runs external process (proc_open)', Category::PROCESS]],
            ],
            'popen($a, $b)' => [
                [['reads from external process (popen)', Category::PROCESS]],
                [['runs external process (popen)', Category::PROCESS]],
            ],
            '`ls`' => [
                [['reads from external process (shell_exec)', Category::PROCESS]],
                [['runs external process (shell_exec)', Category::PROCESS]],
            ],
        ]);
    }

    public function testReportsSendingEmail(): void
    {
        $this->assertFindings([
            'mail($a, $b, $a)' => [[], [['sends email (mail)', Category::MAIL]]],
            'mb_send_mail($a, $b, $a)' => [[], [['sends email (mb_send_mail)', Category::MAIL]]],
        ]);
    }

    /**
     * include and require are language constructs, not calls, and each is
     * named for the construct used.
     */
    public function testReportsIncludingFiles(): void
    {
        $this->assertFindings([
            'include $a' => [[['includes file (include)', Category::INCLUDE]], []],
            'include_once $a' => [[['includes file (include_once)', Category::INCLUDE]], []],
            'require $a' => [[['includes file (require)', Category::INCLUDE]], []],
            'require_once $a' => [[['includes file (require_once)', Category::INCLUDE]], []],
        ]);
    }

    public function testReportsRuntimeConfiguration(): void
    {
        $this->assertFindings([
            'ini_set($a, $b)' => [[], [['writes runtime configuration (ini_set)', Category::RUNTIME_CONFIG]]],
            'set_error_handler($a)' => [[], [['writes runtime configuration (set_error_handler)', Category::RUNTIME_CONFIG]]],
            'set_exception_handler($a)' => [[], [['writes runtime configuration (set_exception_handler)', Category::RUNTIME_CONFIG]]],
            'date_default_timezone_set($a)' => [[], [['writes runtime configuration (date_default_timezone_set)', Category::RUNTIME_CONFIG]]],
            'setlocale($a, $b)' => [[], [['writes runtime configuration (setlocale)', Category::RUNTIME_CONFIG]]],
            'define($a, $b)' => [[], [['writes runtime configuration (define)', Category::RUNTIME_CONFIG]]],
            'register_shutdown_function($a)' => [[], [['writes runtime configuration (register_shutdown_function)', Category::RUNTIME_CONFIG]]],
            'ini_get($a)' => [[['reads runtime configuration (ini_get)', Category::RUNTIME_CONFIG]], []],
            'date_default_timezone_get()' => [[['reads runtime configuration (date_default_timezone_get)', Category::RUNTIME_CONFIG]], []],
        ]);
    }

    /**
     * error_reporting() reads the level; given a level, it sets it.
     */
    public function testReportsErrorReportingByWhetherALevelIsGiven(): void
    {
        $this->assertFindings([
            'error_reporting()' => [[['reads runtime configuration (error_reporting)', Category::RUNTIME_CONFIG]], []],
            'error_reporting($a)' => [[], [['writes runtime configuration (error_reporting)', Category::RUNTIME_CONFIG]]],
        ]);
    }

    public function testReportsSyslogAndTheFilterInputApi(): void
    {
        $this->assertFindings([
            'syslog($a, $b)' => [[], [['writes to error log (syslog)', Category::ERROR_LOG]]],
            'filter_input($a, $b)' => [[['reads from superglobals (filter_input)', Category::SUPERGLOBAL]], []],
            'filter_input_array($a)' => [[['reads from superglobals (filter_input_array)', Category::SUPERGLOBAL]], []],
        ]);
    }

    /**
     * Only a clock read counts: no argument, 'now' in any case, or '', which
     * PHP also treats as now. The class is matched by its resolved name, in
     * any case.
     */
    public function testReportsDateTimeConstructionFromTheClock(): void
    {
        $now = static fn (string $name): array => [[['reads system time (' . $name . ')', Category::TIME]], []];
        $this->assertFindings([
            'new DateTime()' => $now('DateTime'),
            'new \\DateTimeImmutable()' => $now('DateTimeImmutable'),
            'new datetimeimmutable' => $now('datetimeimmutable'),
            "new DateTime('NOW')" => $now('DateTime'),
            "new DateTimeImmutable('')" => $now('DateTimeImmutable'),
            "new DateTimeImmutable('2020-01-01')" => [[], []],
            'new DateTimeImmutable($a)' => [[], []],
            'new DateTimeZone()' => [[], []],
            'date_create()' => $now('date_create'),
            "date_create_immutable('now')" => $now('date_create_immutable'),
            "date_create('')" => $now('date_create'),
            "date_create('2020-01-01')" => [[], []],
            'date_create_immutable($a)' => [[], []],
            'new DateTime(timezone: $a)' => $now('DateTime'),
            "date_create(datetime: '2020-01-01')" => [[], []],
        ]);
    }

    public function testMatchesDateTimeByResolvedName(): void
    {
        $source = "<?php\nnamespace App;\nfunction local() { new DateTime(); }\nfunction fromRoot() { new \\DateTime(); }";

        self::assertSame([[], []], $this->findingsIn($source, 0));
        self::assertSame([[['reads system time (DateTime)', Category::TIME]], []], $this->findingsIn($source, 1));
    }

    /**
     * The date family reads the clock only when its timestamp is omitted or
     * the literal null, by position or by name.
     */
    public function testReportsDateFormattingOnlyWhenItReadsTheClock(): void
    {
        $now = static fn (string $name): array => [[['reads system time (' . $name . ')', Category::TIME]], []];
        $this->assertFindings([
            'hrtime()' => $now('hrtime'),
            "date('Y')" => $now('date'),
            "date('Y', null)" => $now('date'),
            "date('Y', NULL)" => $now('date'),
            "date('Y', timestamp: null)" => $now('date'),
            "date('Y', \$a)" => [[], []],
            "date('Y', timestamp: \$a)" => [[], []],
            "date(format: 'Y')" => $now('date'),
            "gmdate('Y')" => $now('gmdate'),
            "gmdate('Y', \$a)" => [[], []],
            "idate('Y')" => $now('idate'),
            "idate('Y', \$a)" => [[], []],
            'getdate()' => $now('getdate'),
            'getdate(null)' => $now('getdate'),
            'getdate($a)' => [[], []],
            'localtime()' => $now('localtime'),
            'localtime(associative: true)' => $now('localtime'),
            'localtime($a)' => [[], []],
        ]);
    }

    /**
     * PHP fills any mktime argument that's missing or null from the clock.
     */
    public function testReportsMktimeUnlessEveryFieldIsGiven(): void
    {
        $now = static fn (string $name): array => [[['reads system time (' . $name . ')', Category::TIME]], []];
        $this->assertFindings([
            'mktime()' => $now('mktime'),
            'mktime(1, 2, 3, 4, 5)' => $now('mktime'),
            'mktime(1, 2, 3, 4, 5, null)' => $now('mktime'),
            'mktime(null, 2, 3, 4, 5, 6)' => $now('mktime'),
            'mktime(1, 2, 3, 4, 5, $a)' => [[], []],
            'gmmktime(1, 2, 3, 4, 5)' => $now('gmmktime'),
            'gmmktime(1, 2, 3, 4, 5, 6)' => [[], []],
        ]);
    }

    /**
     * A Randomizer built with an explicit engine is seeded by its caller.
     */
    public function testReportsRandomness(): void
    {
        $random = static fn (string $name): array => [[['reads from random number generator (' . $name . ')', Category::RANDOM]], []];
        $this->assertFindings([
            'shuffle($a)' => $random('shuffle'),
            'array_rand($a)' => $random('array_rand'),
            'str_shuffle($a)' => $random('str_shuffle'),
            'lcg_value()' => $random('lcg_value'),
            'uniqid()' => $random('uniqid'),
            'new \\Random\\Randomizer()' => $random('Random\\Randomizer'),
            'new \\random\\randomizer' => $random('random\\randomizer'),
            'new \\Random\\Randomizer($a)' => [[], []],
        ]);
    }

    /**
     * fprintf and vfprintf are file writes whatever the stream, as fwrite is.
     */
    public function testReportsFileAccess(): void
    {
        $read = static fn (string $name): array => [[['reads from file (' . $name . ')', Category::FILE]], []];
        $write = static fn (string $name): array => [[], [['writes to file (' . $name . ')', Category::FILE]]];
        $this->assertFindings([
            'fgets($a)' => $read('fgets'),
            'fgetc($a)' => $read('fgetc'),
            'fgetcsv($a)' => $read('fgetcsv'),
            'fscanf($a, $b)' => $read('fscanf'),
            'file($a)' => $read('file'),
            'stream_get_contents($a)' => $read('stream_get_contents'),
            'fputs($a, $b)' => $write('fputs'),
            'fputcsv($a, $b)' => $write('fputcsv'),
            'fprintf(STDOUT, $a)' => $write('fprintf'),
            'vfprintf($a, $b, [])' => $write('vfprintf'),
            'readfile($a)' => [
                [['reads from file (readfile)', Category::FILE]],
                [['writes to standard output (readfile)', Category::STANDARD_OUTPUT]],
            ],
            'fpassthru($a)' => [
                [['reads from file (fpassthru)', Category::FILE]],
                [['writes to standard output (fpassthru)', Category::STANDARD_OUTPUT]],
            ],
        ]);
    }

    public function testReportsOutputBufferingAsStandardOutput(): void
    {
        $stdout = static fn (string $name): array => [[], [['writes to standard output (' . $name . ')', Category::STANDARD_OUTPUT]]];
        $this->assertFindings([
            'ob_start()' => $stdout('ob_start'),
            'ob_get_clean()' => $stdout('ob_get_clean'),
            'flush()' => $stdout('flush'),
            'my_ob_start()' => [[], []],
        ]);
    }

    public function testReportsFileSystemWrites(): void
    {
        $write = static fn (string $name): array => [[], [['writes to file system (' . $name . ')', Category::FILE_SYSTEM]]];
        $this->assertFindings([
            'unlink($a)' => $write('unlink'),
            'rename($a, $b)' => $write('rename'),
            'mkdir($a)' => $write('mkdir'),
            'rmdir($a)' => $write('rmdir'),
            'copy($a, $b)' => $write('copy'),
            'touch($a)' => $write('touch'),
            'chmod($a, $b)' => $write('chmod'),
            'tmpfile()' => $write('tmpfile'),
            'tempnam($a, $b)' => $write('tempnam'),
        ]);
    }

    /**
     * With a literal true return flag, print_r and var_export return a string
     * and print nothing. Any other flag might print.
     */
    public function testSkipsPrintingFunctionsThatReturnTheirOutput(): void
    {
        $stdout = static fn (string $name): array => [[], [['writes to standard output (' . $name . ')', Category::STANDARD_OUTPUT]]];
        $this->assertFindings([
            'print_r($a)' => $stdout('print_r'),
            'print_r($a, true)' => [[], []],
            'print_r($a, TRUE)' => [[], []],
            'print_r($a, return: true)' => [[], []],
            'print_r($a, false)' => $stdout('print_r'),
            'print_r($a, $b)' => $stdout('print_r'),
            'print_r(true)' => $stdout('print_r'),
            'var_export($a)' => $stdout('var_export'),
            'var_export($a, true)' => [[], []],
            'var_export(return: true, value: $a)' => [[], []],
            'var_export($a, $b)' => $stdout('var_export'),
        ]);
    }

    /**
     * PHP function names are case-insensitive and may be fully qualified.
     * The description keeps the name as written, less the leading "\".
     */
    public function testMatchesNamesInAnyCaseAndFullyQualified(): void
    {
        $this->assertFindings([
            'SHELL_EXEC($a)' => [
                [['reads from external process (SHELL_EXEC)', Category::PROCESS]],
                [['runs external process (SHELL_EXEC)', Category::PROCESS]],
            ],
            '\\mail($a, $b, $a)' => [[], [['sends email (mail)', Category::MAIL]]],
            '\\Error_Reporting($a)' => [[], [['writes runtime configuration (Error_Reporting)', Category::RUNTIME_CONFIG]]],
            "\\Date('Y')" => [[['reads system time (Date)', Category::TIME]], []],
            '\\Print_R($a, True)' => [[], []],
            'OB_START()' => [[], [['writes to standard output (OB_START)', Category::STANDARD_OUTPUT]]],
            'PG_Query($a)' => [
                [['reads from database (PG_Query)', Category::DATABASE]],
                [['writes to database (PG_Query)', Category::DATABASE]],
            ],
        ]);
    }

    /**
     * @param array<string, array{list<array{string, string}>, list<array{string, string}>}> $expected
     *                                                                                                statement => [inputs, outputs]
     */
    protected function assertFindings(array $expected): void
    {
        foreach ($expected as $statement => $findings) {
            self::assertSame($findings, $this->findings($statement), $statement);
        }
    }

    /**
     * @return array{list<array{string, string}>, list<array{string, string}>}
     */
    protected function findings(string $statement): array
    {
        return $this->findingsIn("<?php\nfunction f(\$a, \$b) { {$statement}; }", 0);
    }

    /**
     * @return array{list<array{string, string}>, list<array{string, string}>}
     */
    protected function findingsIn(string $source, int $function): array
    {
        $result = (new SourceChecker())->check($source, new Mode(true, false))[$function];
        $pair = static fn (Finding $finding): array => [$finding->getDescription(), $finding->getCategory()];

        return [array_map($pair, $result->getInputs()), array_map($pair, $result->getOutputs())];
    }
}
