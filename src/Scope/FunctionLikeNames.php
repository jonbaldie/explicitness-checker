<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker\Scope;

/**
 * The names under which function-likes are reported, shared by the CLI and the
 * PHPStan rule so both label the same code identically:
 *
 * - functions: fully qualified, e.g. `App\Sub\fn`,
 * - methods: fully qualified class, e.g. `App\Sub\K::m`,
 * - methods of anonymous classes: `class@anonymous::m`,
 *   or `App\Sub\K::class@anonymous::m` when nested in a named class,
 * - property hooks: the property then the hook, e.g. `App\Sub\K::$p::get`,
 *   or `App\Sub\K::class@anonymous::$p::get` for a nested anonymous class,
 * - closures and arrow functions: `{closure}`.
 */
class FunctionLikeNames
{
    public const CLOSURE = '{closure}';
    public const ANONYMOUS_CLASS = 'class@anonymous';

    /**
     * @param string $namespace the enclosing namespace, "" for none
     */
    public static function qualify(string $namespace, string $name): string
    {
        return $namespace === '' ? $name : $namespace . '\\' . $name;
    }

    /**
     * The class context for an anonymous class, including its nearest named
     * enclosing class when there is one.
     */
    public static function nestedAnonymousClass(string $enclosingClassName): string
    {
        return $enclosingClassName . '::' . self::ANONYMOUS_CLASS;
    }

    /**
     * @param string|null $className class context, null for a top-level anonymous class
     */
    public static function method(?string $className, string $methodName): string
    {
        return ($className ?? self::ANONYMOUS_CLASS) . '::' . $methodName;
    }

    /**
     * A property hook, named after the property it belongs to and its kind
     * (`get` or `set`), so hooks of different properties are told apart.
     *
     * @param string|null $className class context, null for a top-level anonymous class
     */
    public static function hook(?string $className, string $propertyName, string $hookName): string
    {
        return self::method($className, '$' . $propertyName) . '::' . $hookName;
    }
}
