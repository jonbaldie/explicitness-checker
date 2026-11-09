<?php

declare(strict_types=1);

use JonBaldie\ExplicitnessChecker\Walk\AccessRules;
use JonBaldie\ExplicitnessChecker\Walk\ByReferenceParameters;
use JonBaldie\ExplicitnessChecker\Walk\ChildAccessRule;
use JonBaldie\ExplicitnessChecker\Walk\RuleChain;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PhpParser\Node\VariadicPlaceholder;

/**
 * Check every installed php-parser node class with one fabricated instance,
 * plus selected call and variable shapes, in both read and write modes.
 * This is a finite sample of node shapes, not a proof over all instances.
 *
 * Exit 0 means no checked instance was claimed by more than one rule. Exit 1
 * reports overlaps or rules that did not claim any checked instance.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

class ClaimRuleList extends RuleChain
{
    /** @return list<ChildAccessRule> */
    public static function flattened(RuleChain $chain): array
    {
        $rules = [];
        foreach ($chain->rules as $rule) {
            $rules = array_merge($rules, $rule instanceof RuleChain ? self::flattened($rule) : [$rule]);
        }

        return $rules;
    }
}

/**
 * Fixed by-reference catalogue, so the result does not depend on the
 * extensions loaded on the running PHP: sort and preg_match take their first
 * and third argument by reference, strlen takes none.
 */
class FixedByReferenceParameters implements ByReferenceParameters
{
    public function isBuiltin(string $function): bool
    {
        return in_array(strtolower($function), ['sort', 'preg_match', 'strlen'], true);
    }

    public function isPassedByReference(string $function, int $position, ?string $name): bool
    {
        return match (strtolower($function)) {
            'sort' => $name !== null ? $name === 'array' : $position === 0,
            'preg_match' => $name !== null ? $name === 'matches' : $position === 2,
            default => false,
        };
    }
}

/**
 * Builds one instance of every concrete php-parser node class.
 */
class NodeWitnessFactory
{
    /** @var array<class-string<Node>, Node> */
    protected array $built = [];
    /** @var array<string, true> */
    protected array $building = [];

    /**
     * @return list<Node> one witness for each concrete PhpParser\Node class
     */
    public function allWitnesses(): array
    {
        $witnesses = [];
        foreach ($this->concreteNodeClasses() as $class) {
            $witnesses[] = $this->fabricate($class);
        }

        return $witnesses;
    }

    /** @var list<class-string<Node>>|null */
    protected ?array $nodeClasses = null;

    /**
     * @return list<array{Node, string}> witnesses for the nodes whose claims are property-dependent
     */
    public function shapeWitnesses(): array
    {
        $variable = new Expr\Variable('x');
        $string = new Scalar\String_('sort');

        return [
            [new Expr\FuncCall(new Name('sort'), [new Arg($variable)]), 'sort($x)'],
            [new Expr\FuncCall(new Name('sort'), [new Arg($variable, unpack: true)]), 'sort(...$x)'],
            [new Expr\FuncCall(new Name('sort'), [new VariadicPlaceholder()]), 'sort(...)'],
            [new Expr\FuncCall(new Name('call_user_func'), [new Arg($string)]), 'call_user_func(\'sort\')'],
            [new Expr\FuncCall($string, [new Arg($variable)]), '(\'sort\')($x)'],
            [new Expr\FuncCall(new Expr\FuncCall(new Name('sort'), [new VariadicPlaceholder()]), [new Arg($variable)]), '(sort(...))($x)'],
            [new Expr\FuncCall(new Name('strlen'), [new Arg($variable)]), 'strlen($x)'],
            [new Expr\FuncCall(new Name('preg_match'), [new Arg(new Scalar\String_('/x/')), new Arg(new Scalar\String_('x')), new Arg($variable, name: new Node\Identifier('matches'))]), 'preg_match(\'/x/\', \'x\', matches: $x)'],
            [new Expr\FuncCall(new Expr\Variable('f'), [new Arg($variable)]), '$f($x)'],
            [new Expr\Variable(new Expr\Variable('y')), '$$y'],
        ];
    }

    /**
     * @return list<class-string<Node>> concrete, instantiable node classes
     */
    protected function concreteNodeClasses(): array
    {
        if ($this->nodeClasses !== null) {
            return $this->nodeClasses;
        }
        $classes = [];
        $directory = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(dirname(__DIR__) . '/vendor/nikic/php-parser/lib/PhpParser/Node'),
        );
        $root = dirname(__DIR__) . '/vendor/nikic/php-parser/lib/PhpParser/';
        foreach ($directory as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $relative = str_replace([$root, '/', '.php'], ['', '\\', ''], $file->getPathname());
            $class = 'PhpParser\\' . $relative;
            if (!class_exists($class) || !is_subclass_of($class, Node::class)) {
                continue;
            }
            $reflection = new ReflectionClass($class);
            if (strcasecmp($reflection->getName(), $class) !== 0) {
                continue;
            }
            if ($reflection->isInstantiable() && $reflection->implementsInterface(Node::class)) {
                $classes[$class] = true;
            }
        }
        ksort($classes);

        return $this->nodeClasses = array_keys($classes);
    }

    /** @param class-string<Node> $class */
    public function fabricate(string $class): Node
    {
        if (isset($this->built[$class])) {
            return $this->built[$class];
        }
        if (isset($this->building[$class])) {
            throw new RuntimeException('Cycle building ' . $class);
        }

        $this->building[$class] = true;
        try {
            $instance = $this->build($class);
        } finally {
            unset($this->building[$class]);
        }
        $this->built[$class] = $instance;

        return $instance;
    }

    /** @param class-string<Node> $class */
    protected function build(string $class): Node
    {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $candidateLists = [];
        foreach ($constructor->getParameters() as $parameter) {
            $candidateLists[] = $this->candidatesForParameter($parameter);
        }

        return $this->firstInstantiable($reflection, $candidateLists, 0, []);
    }

    /**
     * Some php-parser constructors have untyped parameters and typed
     * properties. Try candidates until construction succeeds.
     *
     * @param ReflectionClass<Node> $reflection
     * @param list<list<mixed>> $candidateLists
     * @param list<mixed> $arguments
     */
    protected function firstInstantiable(ReflectionClass $reflection, array $candidateLists, int $position, array $arguments): Node
    {
        if ($position === count($candidateLists)) {
            set_error_handler(static function (int $severity, string $message): void {
                throw new ErrorException($message, 0, $severity);
            });
            try {
                return $reflection->newInstance(...$arguments);
            } finally {
                restore_error_handler();
            }
        }

        $firstFailure = null;
        foreach ($candidateLists[$position] as $candidate) {
            try {
                return $this->firstInstantiable($reflection, $candidateLists, $position + 1, [...$arguments, $candidate]);
            } catch (Throwable $failure) {
                $firstFailure ??= $failure;
            }
        }

        throw $firstFailure ?? new RuntimeException('No candidate instantiates ' . $reflection->getName());
    }

    /**
     * @return list<mixed> values to try, most promising first
     */
    protected function candidatesForParameter(ReflectionParameter $parameter): array
    {
        $candidates = [];
        if ($parameter->isDefaultValueAvailable()) {
            $candidates[] = $parameter->getDefaultValue();
        }

        $type = $parameter->getType();
        $types = $type instanceof ReflectionUnionType ? $type->getTypes() : ($type !== null ? [$type] : []);
        $named = array_values(array_filter($types, static fn ($member): bool => $member instanceof ReflectionNamedType));
        foreach ($named as $member) {
            $value = $this->valueForNamedType($member);
            if ($value !== null) {
                $candidates[] = $value;
            }
        }

        $shapes = $types === [] || ($named !== [] && $named[0]->getName() === 'array')
            ? [$this->arrayShapeCandidate($parameter)]
            : [];
        foreach ($shapes as $shape) {
            if ($shape !== null) {
                $candidates[] = $shape;
            }
        }

        if ($named === [] || $candidates === []) {
            array_push($candidates, new Expr\Variable('x'), 'x', 0, [], false);
        }
        if ($type === null || $type->allowsNull()) {
            $candidates[] = null;
        }

        return $candidates;
    }

    /**
     * php-parser 5.9's subNodes style takes one array-typed parameter whose
     * required keys and value types the constructor's docblock states. This
     * builds one such array, from the keys the docblock does not mark optional.
     *
     * @return array<string, mixed>|null
     */
    protected function arrayShapeCandidate(ReflectionParameter $parameter): ?array
    {
        $constructor = $parameter->getDeclaringFunction();
        if (!$constructor instanceof ReflectionMethod) {
            return null;
        }
        $doc = $constructor->getDocComment();
        if ($doc === false) {
            return null;
        }
        $name = preg_quote($parameter->getName(), '/');
        if (!preg_match('/@param\s+array\{(.*?)\}\s+\$' . $name . '\b/s', $doc, $match)) {
            return null;
        }

        $value = [];
        foreach (explode("\n", $match[1]) as $line) {
            if (!preg_match('/^\s*\*?\s*([a-zA-Z_]+)(\?)?:\s*(\S+?),?\s*$/', $line, $entry)) {
                continue;
            }
            [, $key, $optional, $entryType] = $entry;
            if ($optional !== '') {
                continue;
            }
            $shapeValue = $this->shapeValue($entryType);
            if ($shapeValue !== null) {
                $value[$key] = $shapeValue;
            }
        }

        return $value === [] ? null : $value;
    }

    /**
     * A value of the docblock type, which is relative to PhpParser\Node.
     */
    protected function shapeValue(string $docType): mixed
    {
        $isList = str_ends_with($docType, '[]');
        $docType = rtrim($isList ? substr($docType, 0, -2) : $docType, ',');
        $members = array_values(array_filter(explode('|', $docType), static fn ($member): bool => $member !== 'null' && $member !== 'mixed'));

        $simple = [
            'bool' => false,
            'int' => 0,
            'string' => 'x',
            'Expr' => new Expr\Variable('x'),
            'Stmt' => new Stmt\Nop(),
            'Name' => new Name('x'),
            'Identifier' => new Node\Identifier('x'),
        ];

        $value = null;
        foreach ($members as $member) {
            if (isset($simple[$member])) {
                $value = $simple[$member];
                break;
            }

            $class = str_starts_with($member, 'Node\\')
                ? 'PhpParser\\' . $member
                : (str_starts_with($member, 'Expr\\') || str_starts_with($member, 'Stmt\\') || str_starts_with($member, 'Scalar\\')
                    ? 'PhpParser\\Node\\' . $member
                    : null);
            if ($class !== null && class_exists($class) && is_subclass_of($class, Node::class) && (new ReflectionClass($class))->isInstantiable()) {
                try {
                    $value = $this->fabricate($class);
                    break;
                } catch (RuntimeException) {
                    continue;
                }
            }
        }

        return $value === null ? null : ($isList ? [$value] : $value);
    }

    protected function valueForNamedType(ReflectionNamedType $type): mixed
    {
        $name = $type->getName();
        switch ($name) {
            case 'string':
                return 'x';
            case 'int':
                return 0;
            case 'bool':
                return false;
            case 'float':
                return 0.0;
            case 'array':
            case 'iterable':
                return [];
            case 'PhpParser\Node\Expr':
                return new Expr\Variable('x');
            case 'PhpParser\Node\Stmt':
                return new Stmt\Nop();
            case 'PhpParser\Node\Name':
                return new Name('x');
            case 'PhpParser\Node\Identifier':
            case 'PhpParser\Node\VarLikeIdentifier':
                return new $name('x');
            case 'PhpParser\Node\Scalar':
                return new Scalar\String_('x');
            case 'PhpParser\Node\ComplexType':
                return new Node\NullableType(new Node\Identifier('x'));
        }

        if (!class_exists($name) || !is_subclass_of($name, Node::class)) {
            return null;
        }

        $reflection = new ReflectionClass($name);
        if ($reflection->isInstantiable()) {
            try {
                return $this->fabricate($name);
            } catch (RuntimeException) {
                return null;
            }
        }

        foreach ($this->concreteNodeClasses() as $candidate) {
            if (is_subclass_of($candidate, $name)) {
                try {
                    return $this->fabricate($candidate);
                } catch (RuntimeException) {
                    return null;
                }
            }
        }

        return null;
    }
}

$rules = ClaimRuleList::flattened(new AccessRules(new FixedByReferenceParameters()));
$factory = new NodeWitnessFactory();

/** @var array<string, list<array{Node, string}>> class => witnesses */
$witnesses = [];
foreach ($factory->allWitnesses() as $witness) {
    $witnesses[$witness::class][] = [$witness, 'fabricated'];
}
foreach ($factory->shapeWitnesses() as [$witness, $shape]) {
    $witnesses[$witness::class][] = [$witness, $shape];
}

/** @var array<string, true> classes some rule claimed at least once */
$claimed = [];
/** @var array<string, true> rules that claimed at least one witness */
$liveRules = [];
$violations = [];

foreach ($witnesses as $class => $classWitnesses) {
    foreach ($classWitnesses as [$witness, $shape]) {
        foreach ([false, true] as $isWrite) {
            $claimants = [];
            foreach ($rules as $rule) {
                if ($rule->children($witness, $isWrite) !== null) {
                    $claimants[] = $rule::class;
                    $liveRules[$rule::class] = true;
                }
            }
            if ($claimants !== []) {
                $claimed[$class] = true;
            }
            if (count($claimants) > 1) {
                $mode = $isWrite ? 'write' : 'read';
                $key = $class . ' [' . $shape . ', ' . $mode . ']: ' . implode(', ', $claimants);
                $violations[$key] = true;
            }
        }
    }
}

$deadRules = array_diff(
    array_map(static fn (ChildAccessRule $rule): string => $rule::class, $rules),
    array_keys($liveRules),
);

printf(
    "%d node classes, %d witnesses, %d rules: %d classes with a claim, %d with none.\n",
    count($witnesses),
    array_sum(array_map('count', $witnesses)),
    count($rules),
    count($claimed),
    count($witnesses) - count($claimed),
);

if ($deadRules !== []) {
    fwrite(STDERR, 'No witness claimed by: ' . implode(', ', $deadRules) . "\n");
}

if ($violations !== []) {
    fwrite(STDERR, "Walk order decides these checked nodes, because multiple rules claim each:\n");
    foreach (array_keys($violations) as $violation) {
        fwrite(STDERR, '  ' . $violation . "\n");
    }
}

if ($deadRules !== [] || $violations !== []) {
    exit(1);
}

echo "No overlaps among the checked witnesses.\n";
exit(0);
