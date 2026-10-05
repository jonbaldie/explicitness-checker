# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.2] - 2026-10-05

### Changed

- Internal refactoring makes the walk's access rules order-independent, with a test that fails if two rules claim the same node (#57), and moves the default `vendor` exclusion into `FileFilter` (#59). No change to findings or CLI output is intended

## [1.2.1] - 2026-10-05

### Changed

- Internal refactoring separates CLI file checking from output narration (#56) and centralises `$GLOBALS` handling (#55). No change to findings or CLI output is intended

## [1.2.0] - 2026-10-05

### Added

- `Finding::getVariable()` returns the name of the global, superglobal, static variable, captured reference or mutated argument a finding is about (e.g. `_ENV`), or null for other findings, so consumers can classify findings without parsing their descriptions. The CLI now recognises `$_ENV` as Critical from this name rather than from the description text; exit codes are unchanged (#36)

### Fixed

- The key of a keyed destructuring item (`[$key => $val] = $data`, `list($key => $val) = $data`, `foreach ($data as [$key => $val])`) is walked as a read instead of a write, so `[$param->key => $val] = $data` no longer reports `wrote to argument $param` and keys that read globals, `$GLOBALS` entries, static properties or (under `--props`) `$this` properties are reported as inputs rather than outputs (#105). This can move rows from outputs to inputs
- Writing to a static property through a dynamic class expression (`$class::$prop = 1`, `$param::$prop = 1`, `$this->class::$prop = 1`, `$GLOBALS['class']::$prop = 1`) walks the class expression as a read instead of a write, so a by-reference parameter used as the class is no longer reported as `wrote to argument`, and globals, `$GLOBALS` entries and (under `--props`) `$this` properties used as the class are reported as inputs rather than outputs (#106). This can move rows from outputs to inputs
- Strict mode matches the call forms of `exit` and `die` case-insensitively, as PHP does, so `\Die(1)` is reported as `terminates the program (die)` and `\DIE('bye')` as `writes to standard output (die)` instead of being ignored (#108). This can add rows under `--strict`
- Strict mode ignores first-class callable creation for impure built-ins and `exit`/`die`, which creates a `Closure` without executing the function or terminating the process (#107). This removes false findings and can lower the strict-mode exit code

## [1.1.2] - 2026-10-01

### Fixed

- Assigning an entry of `$GLOBALS` by reference into an object property, static property or array element (`$this->ref = &$GLOBALS['key']`, `self::$ref = &$GLOBALS['key']`, `$param->ref = &$GLOBALS['key']`, `&$arr['key'] = &$GLOBALS['key']`) is walked as a write to that target and a read+write of the globals entry, rather than erroneously reporting a read of the target and missing argument mutations (#92)
- Reading instance properties on `$this` using the nullsafe operator (`$this?->prop`, `$this?->$prop`) is reported under `--props` and in the PHPStan extension under `props: true` instead of being ignored (#93). This can add rows under `--props`
- `--props` and the PHPStan `props` parameter report `$this` properties with dynamic names (`$this->$prop`, `$this->{$prop}`) as `object property $this->...` instead of ignoring them (#94). This can add rows under `--props`
- Strict mode reads `fopen`'s mode by name as well as position, so `fopen(mode: 'w', filename: $path)` is reported as `writes to file (fopen)` instead of `reads from file (fopen)` (#95). This can move a file row from inputs to outputs

## [1.1.1] - 2026-09-28

### Fixed

- Writing through a property chain (`$this->next->next = null`, `$this->next->items[] = 1`, `self::$head->count = 0`, `global $config; $config->debug = true;`) reports the chain's base as read as well as written, since PHP fetches it before assigning through it (#82). This can add input rows to existing results
- Strict mode reports `ini_alter`, `restore_error_handler` and `restore_exception_handler` as runtime-configuration writes (#83)
- Dynamic `global` declarations are recognized; variable-variable reads and writes after a declaration are reported as global accesses, while declarations remain non-accesses and function and closure boundaries are respected (#85)
- A `global` or `static` declaration of a parameter's name rebinds it for the whole body, as in PHP (#88). `function f($config) { global $config; $config = 1; }` now reports `wrote to global variable $config` instead of nothing, `static $n` over a parameter `$n` is reported as a static variable, and a by-reference parameter or object argument redeclared `global` is reported as the global instead of `wrote to argument`. A name declared both `global` and `static` is reported as the global. This can add rows or change an argument-mutation row to a global-variable row

## [1.1.0] - 2026-09-24

### Added

- Static method calls with no arguments (`SomeClass::method()`) are reported as implicit inputs in default mode, with the PHPStan identifier `explicitness.staticCall`. Calls on `self::`, `parent::` and `static::` are not reported. This changes default-mode results: upgrading can add rows and raise a clean run's exit code to 2
- Writes through arguments, `static` variables and by-reference closure captures are reported in default mode as serious (#72). A write through an argument is a write to a by-reference parameter (`$cart[] = $item` with `array &$cart`) or to a property of an object argument (`$product->price = 1`), under `explicitness.argumentMutation`. `static $x` is read and written under `explicitness.staticVariable`, and `use (&$x)` under `explicitness.capturedReference`. This changes default-mode results: upgrading can add rows and raise a clean run's exit code to 2
- Built-ins that take an argument by reference (`sort($items)`, `preg_match($p, $s, $matches)`) are reported as writing to it, under whichever category owns the variable: a by-reference parameter, a global, a superglobal, a `static` variable or a by-reference capture (#72). By-reference positions come from reflection, so results depend on the PHP extensions loaded where the checker runs. Iterating by reference (`foreach ($cart as &$line)`) and binding a reference (`$r = &$cart`, `[&$first] = $cart`) are reported the same way. `global $list; sort($list);` used to be reported only as a read of `$list`
- Strict mode reports more built-ins (#72): network (`curl_exec`, `fsockopen`, ...), databases (`mysqli_*`, `pg_*`), external processes (`exec`, backticks, ...), email (`mail`), `include`/`require` and runtime configuration (`ini_set`, `set_error_handler`, `define`, ...), under the new critical categories `explicitness.network`, `explicitness.database`, `explicitness.process`, `explicitness.mail`, `explicitness.include` and `explicitness.runtimeConfig`. Existing categories gain more functions: file reads and writes (`fgets`, `fputcsv`, ...), file-system writes (`unlink`, `mkdir`, ...), `ob_*` output buffering, `hrtime`, `shuffle`, `uniqid`, `syslog`, and `filter_input` as a superglobal read. `new DateTime()` and `new DateTimeImmutable()`, with no date or `null`, are reported as reading the clock, and `new Random\Randomizer()` with no engine or `null` as reading randomness. Function names match in any case and fully qualified. This changes strict-mode results: upgrading can add rows and raise a strict run's exit code to 3

### Changed

- Static properties (`Foo::$bar`, `self::$bar`) are reported in default mode instead of only under `--props`, since they are shared state like globals (#72). `--props` and the PHPStan `props` parameter now cover only `$this->x`. This changes default-mode results: upgrading can add rows and raise a clean run's exit code to 2

### Fixed

- A dynamic property name (`$o->{$name}`, `Foo::${$name}`) is reported as read, not written, when the property it names is written (#72)
- `print_r` and `var_export` called with `return: true` are no longer reported as standard output, and `date`, `gmdate`, `idate`, `getdate`, `localtime`, `mktime`, `gmmktime` and `date_create` are no longer reported as reading the clock when they're given a timestamp or date (#72)

- Unreadable files emit a controlled diagnostic to standard error without raw PHP warnings, and are skipped while continuing analysis (#48)
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

[Unreleased]: https://github.com/jonbaldie/explicitness-checker/compare/v1.2.2...HEAD
[1.2.2]: https://github.com/jonbaldie/explicitness-checker/compare/v1.2.1...v1.2.2
[1.2.1]: https://github.com/jonbaldie/explicitness-checker/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/jonbaldie/explicitness-checker/compare/v1.1.2...v1.2.0
[1.1.2]: https://github.com/jonbaldie/explicitness-checker/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/jonbaldie/explicitness-checker/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/jonbaldie/explicitness-checker/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/jonbaldie/explicitness-checker/releases/tag/v1.0.0
