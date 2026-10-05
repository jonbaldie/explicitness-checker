<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\Tests\Support\CheckedFile;
use JonBaldie\ExplicitnessChecker\Tests\Support\Process;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for whether an access is a read or a write (#5, #6, #9, #10).
 *
 * Each test checks a fixture as the CLI does, by default with --props, and
 * compares every report row's implicit inputs and outputs.
 */
class ReadWriteContextTest extends TestCase
{
    protected const REFERENCE_ALIAS_FIXTURE = Process::ROOT . '/tests/Fixtures/reference-global-alias.php';

    protected const REFERENCE_TARGET_FIXTURE = Process::ROOT . '/tests/Fixtures/reference-global-target.php';

    protected const PROPERTY_CHAIN_FIXTURE = Process::ROOT . '/tests/Fixtures/property-chain.php';

    protected const KEYED_DESTRUCTURING_FIXTURE = Process::ROOT . '/tests/Fixtures/keyed-destructuring.php';

    protected const DYNAMIC_STATIC_PROPERTY_CLASS_FIXTURE = Process::ROOT . '/test-fixtures/read-write-context/static-class.php';

    /**
     * #5: the index of an assigned array element is read; only the array is written.
     */
    public function testArrayIndexOnAssignmentTargetIsRead(): void
    {
        self::assertSame(
            [
                'indexBySuperglobal' => [['read from superglobal $_GET'], []],
                'writeGlobalAtGlobalIndex' => [['read from global variable $key'], ['wrote to global variable $map']],
                'nestedIndexes' => [
                    ['read from superglobal $_GET', 'read from superglobal $_POST'],
                    ['wrote to superglobal $_SESSION'],
                ],
                'IndexedCache::put' => [['read from superglobal $_COOKIE'], ['wrote to object property $this->items']],
            ],
            $this->reportedRows('array-index.php'),
        );
    }

    /**
     * #6: `global $x;` alone is not a read.
     */
    public function testGlobalDeclarationIsNotARead(): void
    {
        self::assertSame(
            [
                'writeOnly' => [[], ['wrote to global variable $counter']],
                'readAndWrite' => [['read from global variable $total'], ['wrote to global variable $total']],
            ],
            $this->reportedRows('global-declaration.php'),
        );
    }

    /**
     * #9: foreach key/value targets and catch variables are written.
     */
    public function testForeachTargetsAndCatchVariableAreWritten(): void
    {
        self::assertSame(
            [
                'iterateIntoGlobal' => [['read from global variable $items'], ['wrote to global variable $item']],
                'iterateKeysIntoGlobal' => [['read from superglobal $_POST'], ['wrote to global variable $position']],
                'catchIntoGlobal' => [[], ['wrote to global variable $lastError']],
            ],
            $this->reportedRows('foreach-catch.php'),
        );
    }

    /**
     * #10: unset() writes to what it unsets.
     */
    public function testUnsetIsAWrite(): void
    {
        self::assertSame(
            [
                'logout' => [[], ['wrote to superglobal $_SESSION']],
                'forgetGlobal' => [[], ['wrote to global variable $cache']],
                'forgetGlobalsEntry' => [[], ["wrote to \$GLOBALS['registry']"]],
                'Memo::clear' => [[], ['wrote to object property $this->cached']],
            ],
            $this->reportedRows('unset.php'),
        );
    }

    /**
     * #45: writes through a local reference to a globals-array entry remain
     * writes to that entry.
     */
    public function testReferenceAliasToGlobalsArrayEntryIsAWrite(): void
    {
        self::assertSame(
            [
                'writeThroughAlias' => [[], ["wrote to \$GLOBALS['counter']"]],
                'readWriteThroughAlias' => [["read from \$GLOBALS['total']"], ["wrote to \$GLOBALS['total']"]],
                'incrementAndDecrementThroughAliases' => [
                    ["read from \$GLOBALS['up']", "read from \$GLOBALS['down']"],
                    ["wrote to \$GLOBALS['up']", "wrote to \$GLOBALS['down']"],
                ],
                'unsetThroughAlias' => [[], ["wrote to \$GLOBALS['removed']"]],
                'rebindAlias' => [[], ["wrote to \$GLOBALS['second']"]],
                'dynamicGlobalKey' => [[], ['wrote to $GLOBALS[$key]']],
                'parameterReference' => [[], ['wrote to argument $value']],
                'nonGlobalsReference' => [['read from superglobal $_SESSION'], ['wrote to superglobal $_SESSION']],
            ],
            $this->reportedRowsAtPath(self::REFERENCE_ALIAS_FIXTURE, ['--strict', '--props']),
        );
    }

    /**
     * #82: writing through a property chain reads the chain's base before
     * writing through it; a direct write and a read-only chain are unchanged.
     */
    public function testWriteThroughPropertyChainReadsItsBase(): void
    {
        self::assertSame(
            [
                'Node::unlink' => [['read from object property $this->next'], ['wrote to object property $this->next']],
                'Node::append' => [['read from object property $this->next'], ['wrote to object property $this->next']],
                'Node::resetHead' => [
                    ['read from static property self::$head'],
                    ['wrote to static property self::$head'],
                ],
                'Node::direct' => [[], ['wrote to object property $this->count']],
                'Node::readChain' => [['read from object property $this->next'], []],
                'write_through_global' => [['read from global variable $config'], ['wrote to global variable $config']],
            ],
            $this->reportedRowsAtPath(self::PROPERTY_CHAIN_FIXTURE),
        );
    }

    /**
     * #92: assigning an entry of $GLOBALS by reference to an object property,
     * static property or argument writes to that target as well as reading and
     * writing the globals entry.
     */
    public function testReferenceAssignmentOfGlobalsToNonVariablesIsAWrite(): void
    {
        self::assertSame(
            [
                'RefBug::assignThis' => [
                    ["read from \$GLOBALS['counter']"],
                    ["wrote to object property \$this->ref", "wrote to \$GLOBALS['counter']"],
                ],
                'RefBug::assignStatic' => [
                    ["read from \$GLOBALS['counter']"],
                    ["wrote to static property self::\$staticRef", "wrote to \$GLOBALS['counter']"],
                ],
                'mutateParamRef' => [
                    ["read from \$GLOBALS['counter']"],
                    ["wrote to argument \$param", "wrote to \$GLOBALS['counter']"],
                ],
                'mutateParamArrayRef' => [
                    ["read from \$GLOBALS['counter']"],
                    ["wrote to argument \$arr", "wrote to \$GLOBALS['counter']"],
                ],
            ],
            $this->reportedRowsAtPath(self::REFERENCE_TARGET_FIXTURE),
        );
    }

    /**
     * #106: a dynamic class expression is read when writing to its static property.
     */
    public function testDynamicClassExpressionOnStaticPropertyWriteIsRead(): void
    {
        self::assertSame(
            [
                'DynamicClassProbe::writeThroughParam' => [[], ['wrote to static property ...::$value']],
                'DynamicClassProbe::writeThroughGlobal' => [
                    ['read from global variable $className'],
                    ['wrote to static property ...::$value'],
                ],
                'DynamicClassProbe::writeThroughProperty' => [
                    ['read from object property $this->className'],
                    ['wrote to static property ...::$value'],
                ],
                'DynamicClassProbe::writeThroughGlobals' => [
                    ["read from \$GLOBALS['className']"],
                    ['wrote to static property ...::$value'],
                ],
                'DynamicClassProbe::readThroughParam' => [['read from static property ...::$value'], []],
            ],
            $this->reportedRowsAtPath(self::DYNAMIC_STATIC_PROPERTY_CLASS_FIXTURE, ['--props']),
        );
    }

    /**
     * #105: the key of a keyed destructuring item is read; only its value is
     * written.
     */
    public function testKeyedDestructuringKeyIsRead(): void
    {
        self::assertSame(
            [
                'KeyedDestructureProbe::readKeyThroughProp' => [['read from object property $this->key'], []],
                'KeyedDestructureProbe::writeValueToProp' => [[], ['wrote to object property $this->value']],
                'KeyedDestructureProbe::readKeyInNestedList' => [['read from object property $this->key'], []],
                'read_global_key' => [['read from global variable $key'], []],
                'read_globals_array_key' => [["read from \$GLOBALS['key']"], []],
                'read_static_key' => [['read from static property KeyedDestructureProbe::$staticKey'], []],
                'read_foreach_key' => [['read from global variable $key'], []],
                'write_global_value' => [[], ['wrote to global variable $out']],
            ],
            $this->reportedRowsAtPath(self::KEYED_DESTRUCTURING_FIXTURE),
        );
    }

    /**
     * Checks the fixture as the CLI does with --props and returns its report rows.
     *
     * @return array<string, array{list<string>, list<string>}> function name => [inputs, outputs]
     */
    protected function reportedRows(string $fixture): array
    {
        return $this->reportedRowsAtPath(Process::ROOT . '/test-fixtures/read-write-context/' . $fixture);
    }

    /**
     * @param list<string> $flags
     *
     * @return array<string, array{list<string>, list<string>}> function name => [inputs, outputs]
     */
    protected function reportedRowsAtPath(string $path, array $flags = ['--props']): array
    {
        $rows = [];
        foreach (CheckedFile::rows($path, $flags) as [, $function, $inputs, $outputs]) {
            $rows[$function] = [$inputs, $outputs];
        }

        return $rows;
    }
}
