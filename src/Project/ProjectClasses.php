<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Project;

use JonBaldie\ExplicitnessChecker\ChildNodes;
use JonBaldie\ExplicitnessChecker\Cli\FileCheckResult;
use PhpParser\Node;
use PhpParser\Node\Stmt;

final class ProjectClasses
{
    /** @var array<int, string|null> lexical class for each function-like */
    protected array $contexts = [];

    /** @var array<string, list<string|null>> one entry per named class declaration */
    protected array $parents = [];

    /** @param list<FileCheckResult> $files */
    public function __construct(array $files)
    {
        foreach ($files as $file) {
            foreach ($file->getAst() as $node) {
                [$contexts, $parents] = self::collect($node, null);
                $this->contexts += $contexts;
                foreach ($parents as $class => $entries) {
                    $this->parents[$class] = array_merge($this->parents[$class] ?? [], $entries);
                }
            }
        }
    }

    public function context(Node\FunctionLike $function): ?string
    {
        return $this->contexts[spl_object_id($function)] ?? null;
    }

    public function isUnique(string $class): bool
    {
        return count($this->parents[$class] ?? []) === 1;
    }

    public function parentOf(string $class): ?string
    {
        return $this->isUnique($class) ? $this->parents[$class][0] : null;
    }

    /** @return array{array<int, string|null>, array<string, list<string|null>>} */
    protected static function collect(Node $node, ?string $class): array
    {
        $parents = [];
        if ($node instanceof Stmt\ClassLike) {
            $class = self::className($node);
            if ($class !== null) {
                $parents[$class] = [self::parentName($node)];
            }
        }
        if ($node instanceof Stmt\Function_) {
            $class = null;
        }
        $contexts = $node instanceof Node\FunctionLike ? [spl_object_id($node) => $class] : [];
        foreach (ChildNodes::of($node) as $child) {
            [$childContexts, $childParents] = self::collect($child, $class);
            $contexts += $childContexts;
            foreach ($childParents as $name => $entries) {
                $parents[$name] = array_merge($parents[$name] ?? [], $entries);
            }
        }

        return [$contexts, $parents];
    }
    protected static function className(Stmt\ClassLike $node): ?string
    {
        if (!$node instanceof Stmt\Class_ || $node->isAnonymous() || $node->namespacedName === null) {
            return null;
        }

        return strtolower($node->namespacedName->toString());
    }

    protected static function parentName(Stmt\ClassLike $node): ?string
    {
        if (!$node instanceof Stmt\Class_ || $node->extends === null) {
            return null;
        }

        return strtolower($node->extends->toString());
    }

}
