# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Static method calls with no arguments (`SomeClass::method()`) are reported as implicit inputs in default mode, with the PHPStan identifier `explicitness.staticCall`. Calls on `self::`, `parent::` and `static::` are not reported. This changes default-mode results: upgrading can add rows and raise a clean run's exit code to 2

### Fixed

- Unknown `-`-prefixed CLI options fail the run with exit code 2 instead of being ignored, so a mistyped `--strict` cannot turn the check off (#23)

## [1.0.0] - 2026-09-21

First tagged release of the CLI and the PHPStan extension.

### Added

- CLI (`bin/explicitness-checker`) that reports implicit inputs and outputs in PHP functions and methods
- PHPStan extension with `explicitness.<category>` identifiers, sharing the CLI's analyser
- `--verbose`, `--strict`, `--props`, `--exclude`, `--include-pattern`, and `--exclude-pattern`
- Severity-based exit codes (0–3) for CI
- Detection of globals, superglobals, and `$GLOBALS`; with flags, stdout, file I/O, environment, time, random, HTTP headers, sessions, error logging, and property access
- Function-like names: fully qualified functions and methods, `class@anonymous::method`, `{closure}`, and PHP 8.4 property hooks as `Class::$property::get` / `Class::$property::set`

### Fixed

- Invalid `--include-pattern` / `--exclude-pattern` regexes fail the run instead of being ignored (#22)
- CLI File column prints the walked path (#24)
- README headline example is PHP the CLI actually reports (#25)
- Repeated `--exclude-pattern` / `--include-pattern` flags accumulate (#26)
- PHP 8.4 property hooks are named after their property and hook instead of `{closure}` (#27)

[Unreleased]: https://github.com/jonbaldie/explicitness-checker/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/jonbaldie/explicitness-checker/releases/tag/v1.0.0
