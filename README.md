This is an **explicitness parsing** tool that you can run on your PHP projects to analyze the explicitness of your code.

## What is explicitness?

Simply put, explicitness refers to the clarity of data flow through your functions. Ideally all of your inputs should be provided through function arguments, and all of your outputs should be returned through function return values.

```php
function add($a, $b) {
    return $a + $b; // explicit input and output: all values are provided through function arguments and returned through function return values
}
```

If your function accesses global variables not provided through its arguments, that is an implicit input. If your function changes global state without returning a value, that is an implicit output.

```php
$some_global_number = 10;

function add($a, $b) {
    echo "Calling add on $a and $b...\n"; // implicit output: printing to stdout

    --$some_global_number; // implicit output: decrementing global variable $some_global_number

    return $a + $b + $some_global_number; // implicit input: reading from global variable $some_global_number
}
```

## How does the tool work?

The tool uses nikic's PHP Parser library to parse PHP code and analyze the explicitness of your functions and class methods.

### Which functions are checked, and how they're named

Every function-like with a body is checked on its own, wherever it's declared, and whether or not the file declares a namespace:

| Declared as | Reported as |
|---|---|
| Function (including inside `if` blocks or other functions) | Fully qualified name, e.g. `App\Sub\send_mail` |
| Method of a named class, trait or enum | Fully qualified class, e.g. `App\Sub\Mailer::send` |
| Method of an anonymous class (`new class { ... }`) | `class@anonymous::send` |
| Closure or arrow function | `{closure}` |

Abstract and interface methods have no body and aren't checked. Rows are listed in source order.

A function's analysis stops at any closure, arrow function, nested function or class declared inside it: code in there, including `global` declarations, belongs to that inner function-like, which gets its own row. So a closure that declares `global $x` doesn't make the enclosing function's local `$x` look like a global.

**Behaviour change:** earlier versions skipped closures, arrow functions, anonymous-class methods and conditionally declared functions in files without a namespace, dropped the namespace from method names, and let a closure's `global` declarations leak into the enclosing function. Upgrading can therefore add rows, remove false `global` findings from enclosing functions, rename methods, and change the exit code.

## Why should I care about explicitness?

**Implicit inputs and outputs fundamentally limit the modularity and reusability of your code.**

Functions with implicit dependencies are like electronic components that are hardwired to other components - they can't be easily detached and used elsewhere. When a function reads from global variables (implicit inputs) or writes to global state or performs side effects like DOM manipulation (implicit outputs), it becomes tightly coupled to its environment. This means you can only use that function in very specific contexts where those global dependencies are available and properly configured. In contrast, functions with explicit inputs (arguments) and outputs (return values) are like modular connectors that can be plugged into any compatible system.

**The practical consequences of implicit inputs and outputs make your code significantly harder to test, debug, and reason about.**

Implicit inputs limit when you can call a function because you must ensure all the global state is properly set up beforehand, and you have to worry about other code potentially interfering with those shared variables. Implicit outputs similarly constrain when you can call a function - you can only call it when you actually want those side effects to occur. This makes testing particularly challenging because you must set up all the implicit inputs, run the function, and then verify all the implicit outputs, which becomes exponentially more complex as the number of implicit dependencies grows. Functions with only explicit inputs and outputs are much easier to test because you simply pass in arguments and check the return value, with no external setup or cleanup required.

## Installation

Install via Composer:

```bash
composer require jonbaldie/explicitness-checker --dev
```

## How do I run the tool?

```bash
./vendor/bin/explicitness-checker [--verbose] [--strict] [--props] [--exclude=dir] [--include-pattern=pattern] [--exclude-pattern=pattern] ./path/to/your/project
```

### Flags

- `--verbose` or `-v`: Enable verbose output showing detailed analysis progress
- `--strict`: Enable strict mode which detects additional implicit I/O patterns:
  - File I/O operations (file_get_contents, fwrite, etc.)
  - Standard output operations (echo, print, printf, etc.)
- `--props`: Enable implicit property access detection for object-oriented code:
  - Implicit instance property access (`$this->property`)
  - Implicit static property access (`ClassName::$property`)

### Directory and File Filtering

- `--exclude=directory` or `--exclude directory`: Exclude specific directories from analysis
  - Can be used multiple times to exclude multiple directories
  - By default, `vendor/` is excluded
  - Example: `--exclude=tests --exclude=cache`
- `--include-pattern=pattern` or `--include-pattern pattern`: Only analyze files matching the regex pattern
  - Example: `--include-pattern="src/.*\.php$"` to only analyze PHP files in src/
- `--exclude-pattern=pattern` or `--exclude-pattern pattern`: Exclude files matching the regex pattern
  - Example: `--exclude-pattern="test.*\.php$"` to exclude test files

#### Filtering Examples

```bash
# Exclude vendor and tests directories
./vendor/bin/explicitness-checker --exclude=vendor --exclude=tests ./project

# Only analyze files in src/ directory
./vendor/bin/explicitness-checker --include-pattern="src/" ./project

# Exclude all test files
./vendor/bin/explicitness-checker --exclude-pattern="test.*\.php$" ./project

# Combine multiple filters
./vendor/bin/explicitness-checker --exclude=vendor --exclude-pattern=".*Test\.php$" ./project
```

### Severity Levels and Exit Codes

The tool categorizes violations into three severity levels:

- **Minor** (Exit code 1): Simple output operations
  - `echo`, `print`, `var_dump`, `print_r`
- **Serious** (Exit code 2): Global state access and property violations
  - Global variables (`global`, `$GLOBALS`)
  - Superglobals (`$_GET`, `$_POST`, `$_SESSION`, etc.)
  - Property access (`$this->property`, `ClassName::$property`) when `--props` is enabled
- **Critical** (Exit code 3): System-level implicit I/O
  - File operations (`file_get_contents`, `fwrite`, etc.)
  - Environment access (`getenv`, `$_ENV`)
  - Time functions (`time`, `date`, `microtime`)
  - Random functions (`rand`, `random_int`)
  - HTTP headers (`header`, `setcookie`)
  - Session functions (`session_start`, `session_id`)
  - Error logging (`error_log`, `trigger_error`)

**Behaviour change:** `$_ENV` access used to be Serious, like the other superglobals. It is now Critical, the same as `getenv`, so a run whose worst finding is `$_ENV` access now exits with 3 instead of 2.

#### Exit Codes for CI Integration

- **0**: No violations found
- **1**: Only minor violations found
- **2**: Serious violations found (may include minor)
- **3**: Critical violations found (may include serious and minor)

The tool exits with the highest severity level found, making it easy to integrate into CI pipelines with appropriate failure thresholds.

### Example Output

```
$ ./vendor/bin/explicitness-checker ./path/to/your/project

Analyzing ./path/to/your/project

Thinking...

Results:

| File | Line | Function | Implicit Inputs | Implicit Outputs |
|------|------|----------|-----------------|------------------|
| Calculator.php | 12 | add | read from global variable $some_global_number | wrote to global variable $some_global_number |
```

## PHPStan extension

This package also ships a [PHPStan](https://phpstan.org/) rule that reports the same implicit inputs and outputs as the CLI, as PHPStan errors. It finds and names function-likes the same way the CLI does (see "[Which functions are checked, and how they're named](#which-functions-are-checked-and-how-theyre-named)" above): a function-like the CLI reports is reported by the rule under the same name, wherever it's declared. The one thing the extension doesn't inherit from the CLI is file selection: the CLI walks the path you give it, filtered by `--exclude`/`--include-pattern`/`--exclude-pattern`; the rule analyses whatever `paths` (and `excludePaths`) your own PHPStan configuration already includes. See [#12](https://github.com/jonbaldie/explicitness-checker/issues/12) for the history here.

### Installation

With [`phpstan/extension-installer`](https://github.com/phpstan/extension-installer), the extension is picked up automatically once both packages are required — no further configuration needed:

```bash
composer require --dev phpstan/extension-installer jonbaldie/explicitness-checker
```

Without it, require the package and include its config yourself:

```bash
composer require --dev jonbaldie/explicitness-checker
```

```neon
# phpstan.neon
includes:
    - vendor/jonbaldie/explicitness-checker/extension.neon
```

Either way, run `vendor/bin/phpstan analyse` as usual; violations appear alongside your other PHPStan errors.

### `strict` and `props` parameters

`explicitness.strict` and `explicitness.props` mirror the CLI's `--strict` and `--props` flags and both default to `false`:

```neon
parameters:
    explicitness:
        strict: true
        props: true
```

### Categories and identifiers

Every error the rule reports carries one of these `explicitness.<category>` identifiers. Categories marked `strict` or `props` only appear once the matching parameter above is `true`; the rest are always on.

| Category | Identifier | Enabled by |
|---|---|---|
| Global variable (`global $x`) | `explicitness.globalVariable` | default |
| Superglobal (`$_GET`, `$_ENV`, etc.) | `explicitness.superglobal` | default |
| `$GLOBALS` array | `explicitness.globalsArray` | default |
| Standard output (`echo`, `print`, `var_dump`, ...) | `explicitness.standardOutput` | `strict` |
| File I/O (`file_get_contents`, `fwrite`, ...) | `explicitness.file` | `strict` |
| File system checks (`file_exists`, `is_dir`, ...) | `explicitness.fileSystem` | `strict` |
| Environment variables (`getenv`, `putenv`) | `explicitness.environment` | `strict` |
| System time (`time`, `date`, `microtime`, ...) | `explicitness.time` | `strict` |
| Random number generator (`rand`, `random_int`, ...) | `explicitness.random` | `strict` |
| HTTP headers (`header`, `setcookie`, ...) | `explicitness.httpHeaders` | `strict` |
| Error log (`error_log`, `trigger_error`, ...) | `explicitness.errorLog` | `strict` |
| Session (`session_start`, `session_id`, ...) | `explicitness.session` | `strict` |
| Object property (`$this->property`) | `explicitness.objectProperty` | `props` |
| Static property (`ClassName::$property`) | `explicitness.staticProperty` | `props` |

### Ignoring or baselining a category

To ignore a category everywhere, add it to `ignoreErrors` by identifier:

```neon
parameters:
    ignoreErrors:
        - identifier: explicitness.standardOutput
```

To grandfather in existing violations instead, run `vendor/bin/phpstan analyse --generate-baseline`. Each generated baseline entry keeps its `identifier`, so you can hand-edit `phpstan-baseline.neon` afterwards to drop the entries for a category you'd rather start enforcing straight away.

### Overlap with `jonbaldie/phpstan-extension-accessing-globals`

[`jonbaldie/phpstan-extension-accessing-globals`](https://github.com/jonbaldie/phpstan-extension-accessing-globals) also reports on global and superglobal access, under its own identifiers. If you install both, the same line can be reported twice:

- `explicitness.globalVariable` and `explicitness.globalsArray` overlap with that extension's default `access.global` and `modify.global` identifiers (`global $x` and `$GLOBALS[...]` access and modification).
- `explicitness.superglobal` overlaps with its default `access.superglobal.nested` and `modify.superglobal.nested` identifiers, since this extension only ever checks function bodies, which that extension always treats as a nested scope.
- `explicitness.staticProperty` (with `explicitness.props: true`) overlaps with its opinionated `property.static` identifier, if you've enabled that extension's opinionated rule set.
- With `explicitness.strict: true`, `explicitness.time`, `explicitness.random`, `explicitness.environment`, `explicitness.file`, `explicitness.fileSystem`, `explicitness.httpHeaders`, `explicitness.errorLog` and `explicitness.session` overlap with its opinionated `function.impure` identifier, which reports calls to a fixed list of impure built-in functions. The lists only partly match: `function.impure` also covers functions this extension doesn't (`strtotime`, `unlink`, `exec`, ...), and doesn't cover `srand`, `mt_srand`, `setrawcookie`, `http_response_code`, `trigger_error`, `user_error` or `session_write_close`. `explicitness.standardOutput` doesn't overlap.

Pick one extension as the source of truth for each pair and ignore the other's identifier for it, e.g. to keep `jonbaldie/phpstan-extension-accessing-globals` as the source of truth for global and superglobal access:

```neon
parameters:
    ignoreErrors:
        - identifier: explicitness.globalVariable
        - identifier: explicitness.globalsArray
        - identifier: explicitness.superglobal
```

For `function.impure`, neither side is a superset of the other, so ignoring either one loses some coverage. If you run both extensions strictly, one option is to keep this extension's per-category identifiers and ignore `function.impure`:

```neon
parameters:
    ignoreErrors:
        - identifier: function.impure
```
