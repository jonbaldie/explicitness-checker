<?php

declare(strict_types=1);

namespace JonBaldie\ExplicitnessChecker;

/**
 * The kinds of implicit input or output a Finding can belong to.
 *
 * Values are safe to use as PHPStan identifier segments (`explicitness.<category>`).
 */
class Category
{
    public const GLOBAL_VARIABLE = 'globalVariable';
    public const SUPERGLOBAL = 'superglobal';
    public const GLOBALS_ARRAY = 'globalsArray';
    public const STANDARD_OUTPUT = 'standardOutput';
    public const FILE = 'file';
    public const FILE_SYSTEM = 'fileSystem';
    public const ENVIRONMENT = 'environment';
    public const TIME = 'time';
    public const RANDOM = 'random';
    public const HTTP_HEADERS = 'httpHeaders';
    public const ERROR_LOG = 'errorLog';
    public const SESSION = 'session';
    public const OBJECT_PROPERTY = 'objectProperty';
    public const STATIC_PROPERTY = 'staticProperty';

    public const ALL = [
        self::GLOBAL_VARIABLE,
        self::SUPERGLOBAL,
        self::GLOBALS_ARRAY,
        self::STANDARD_OUTPUT,
        self::FILE,
        self::FILE_SYSTEM,
        self::ENVIRONMENT,
        self::TIME,
        self::RANDOM,
        self::HTTP_HEADERS,
        self::ERROR_LOG,
        self::SESSION,
        self::OBJECT_PROPERTY,
        self::STATIC_PROPERTY,
    ];
}
