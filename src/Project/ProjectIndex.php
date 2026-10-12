<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Project;

use JonBaldie\ExplicitnessChecker\Cli\FileCheckResult;
use JonBaldie\ExplicitnessChecker\Scope\FunctionLikeFinder;
use PhpParser\Node;
use PhpParser\Node\Stmt;

final class ProjectIndex
{
    /** @var array<string, ProjectFunction> */
    protected array $functions = [];

    /** @var array<string, list<string>> */
    protected array $names = [];

    protected ProjectClasses $classes;

    /** @var array<string, list<string>> */
    protected array $methods = [];

    /** @param list<FileCheckResult> $files */
    public function __construct(array $files)
    {
        $this->classes = new ProjectClasses($files);
        foreach ($files as $file) {
            foreach ((new FunctionLikeFinder())->find($file->getAst()) as $position => $declaration) {
                $id = self::identity($file->getFile(), $position);
                $result = $file->getFunctions()[$position];
                $this->functions[$id] = new ProjectFunction($id, $declaration->getNode(), $result, $this->classes->context($declaration->getNode()));
                if ($declaration->getNode() instanceof Stmt\Function_) {
                    $this->names[strtolower($result->getName())][] = $id;
                }
                $class = $this->classes->context($declaration->getNode());
                if ($class !== null && $declaration->getNode() instanceof Stmt\ClassMethod) {
                    $this->methods[$class . '::' . strtolower($declaration->getNode()->name->toString())][] = $id;
                }
            }
        }
    }

    public static function identity(string $file, int $position): string
    {
        return $file . "\0" . $position;
    }

    /** @return array<string, ProjectFunction> */
    public function functions(): array
    {
        return $this->functions;
    }

    public function parentOf(string $class): ?string
    {
        return $this->classes->parentOf($class);
    }

    public function method(?string $class, string $name): ?ProjectFunction
    {
        $seen = [];
        while ($class !== null && !isset($seen[$class])) {
            if (!$this->classes->isUnique($class)) {
                return null;
            }
            $seen[$class] = true;
            $ids = $this->methods[$class . '::' . strtolower($name)] ?? [];
            if ($ids !== []) {
                return count($ids) === 1 ? $this->functions[$ids[0]] : null;
            }
            $class = $this->classes->parentOf($class);
        }

        return null;
    }

    public function callee(Node\Name $name): ?ProjectFunction
    {
        $candidates = [$name->toString()];
        $namespaced = $name->getAttribute('namespacedName');
        if ($namespaced instanceof Node\Name) {
            array_unshift($candidates, $namespaced->toString());
        }
        foreach ($candidates as $candidate) {
            $ids = $this->names[strtolower($candidate)] ?? [];
            if ($ids !== []) {
                return count($ids) === 1 ? $this->functions[$ids[0]] : null;
            }
        }

        return null;
    }
}
