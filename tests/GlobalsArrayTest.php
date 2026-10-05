<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Tests;

use JonBaldie\ExplicitnessChecker\GlobalsArray;
use JonBaldie\ExplicitnessChecker\Tests\Support\Process;
use JonBaldie\ExplicitnessChecker\Walk\AccessRules;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

/**
 * #55: one module owns what a `$GLOBALS` access is; the walk and the
 * detectors both consult it.
 */
class GlobalsArrayTest extends TestCase
{
    /**
     * A `$GLOBALS[...]` fetch is reported as a whole: the rules chain walks
     * none of its children, in either mode, whatever its dimension.
     */
    public function testGlobalsFetchYieldsNoChildrenThroughTheRulesChain(): void
    {
        $rules = new AccessRules();

        foreach (['$GLOBALS[$key]', "\$GLOBALS['x']", '$GLOBALS[]'] as $source) {
            $fetch = $this->assignedTo($source . ' = 1;');

            self::assertTrue(GlobalsArray::isFetch($fetch), $source);
            self::assertSame([], $rules->childrenOf($fetch, true), $source);
            self::assertSame([], $rules->childrenOf($fetch, false), $source);
        }
    }

    /**
     * Any other array fetch walks its array in the node's mode and reads its index.
     */
    public function testOtherArrayFetchesWalkArrayAndIndex(): void
    {
        $fetch = $this->assignedTo('$map[$key] = 1;');

        self::assertFalse(GlobalsArray::isFetch($fetch));
        self::assertSame([[$fetch->var, true], [$fetch->dim, false]], (new AccessRules())->childrenOf($fetch, true));
    }

    public function testSubjectNamesLiteralAndVariableKeys(): void
    {
        $subjects = [];
        foreach (["\$GLOBALS['x']", '$GLOBALS[3]', '$GLOBALS[$key]', '$GLOBALS[$a . $b]', '$GLOBALS[]', '$map[1]'] as $source) {
            $subjects[$source] = GlobalsArray::subjectOf($this->assignedTo($source . ' = 1;'));
        }

        self::assertSame(
            [
                "\$GLOBALS['x']" => "\$GLOBALS['x']",
                '$GLOBALS[3]' => '$GLOBALS[3]',
                '$GLOBALS[$key]' => '$GLOBALS[$key]',
                '$GLOBALS[$a . $b]' => '$GLOBALS',
                '$GLOBALS[]' => '$GLOBALS',
                '$map[1]' => null,
            ],
            $subjects,
        );
    }

    /**
     * Walk runs detectors, so Detect must never depend on Walk; and Walk asks
     * GlobalsArray, not a detector, what a `$GLOBALS` fetch is.
     */
    public function testDetectAndWalkDependOnEachOtherInOneDirectionOnly(): void
    {
        self::assertSame([], $this->importsBetween('Detect', 'JonBaldie\\ExplicitnessChecker\\Walk\\'));
        self::assertSame([], $this->importsBetween('Walk', 'JonBaldie\\ExplicitnessChecker\\Detect\\GlobalsArrayDetector'));
    }

    protected function assignedTo(string $source): Expr\ArrayDimFetch
    {
        $statements = (new ParserFactory())->createForHostVersion()->parse('<?php ' . $source) ?? [];
        $statement = $statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        self::assertInstanceOf(Expr\Assign::class, $statement->expr);
        self::assertInstanceOf(Expr\ArrayDimFetch::class, $statement->expr->var);

        return $statement->expr->var;
    }

    /**
     * @return list<string> "file: use ..." for each import in src/$package starting with $prefix
     */
    protected function importsBetween(string $package, string $prefix): array
    {
        $imports = [];
        foreach (glob(Process::ROOT . '/src/' . $package . '/*.php') ?: [] as $path) {
            foreach (file($path) ?: [] as $line) {
                if (str_starts_with($line, 'use ' . $prefix)) {
                    $imports[] = basename($path) . ': ' . trim($line);
                }
            }
        }

        return $imports;
    }
}
