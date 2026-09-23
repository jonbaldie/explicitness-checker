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
    global $some_global_number;

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
| Method of an anonymous class (`new class { ... }`) | `class@anonymous::send`, or `App\Service::class@anonymous::send` when nested in a named class |
| Property hook (PHP 8.4 `get`/`set`), including on a promoted constructor parameter | Fully qualified class, property and hook, e.g. `App\Sub\Temperature::$celsius::get`, or `class@anonymous::$celsius::get` for an anonymous class; `App\Service::class@anonymous::$celsius::get` when nested in a named class |
| Closure or arrow function | `{closure}` |

Abstract and interface methods, and hooks declared without a body, aren't checked. Hooks are reported even when the tool itself runs on an older PHP version. Rows are listed in source order.

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

### Laravel

Run the checker on `app`:

```bash
./vendor/bin/explicitness-checker app
```

- **Blind spot.** Helpers (`env()`, `config()`, `request()`, `now()`, ...) and facade calls that take arguments (`DB::table('orders')`, `Cache::get($key)`, `Log::info($message)`, ...) are ordinary calls to the checker, so I/O through them reports as explicit. Facade calls with no arguments, such as `Auth::user()`, are reported as static method calls. A clean result covers only superglobals, `global`, `$GLOBALS`, static method calls with no arguments, static properties, writes through arguments, `static` variables, by-reference closure captures, and with `--strict` built-ins such as `getenv`, `time` and `file_get_contents`.
- **`--props`** also reports constructor-injected services (`$this->orders`), so expect it to flag most controllers and services.
- **PHPStan with Larastan.** Add this package's config next to the Larastan include from Larastan's docs:

  ```neon
  includes:
      - vendor/larastan/larastan/extension.neon
      - vendor/jonbaldie/explicitness-checker/extension.neon
  ```

  With `phpstan/extension-installer`, delete both lines instead. The installer loads both extensions, and a file included twice makes PHPStan abort with "This file is included multiple times".

## How do I run the tool?

```bash
./vendor/bin/explicitness-checker [--verbose] [--strict] [--props] [--exclude=dir] [--include-pattern=pattern] [--exclude-pattern=pattern] [--min-explicitness=percent] ./path/to/your/project
```

### Flags

- `--verbose` or `-v`: Enable verbose output showing detailed analysis progress
- `--strict`: Enable strict mode which detects additional implicit I/O patterns:
  - File I/O operations (file_get_contents, fwrite, etc.)
  - Standard output operations (echo, print, printf, etc.)
- `--props`: Also report instance property access (`$this->property`). Static properties are reported without it.

Any other argument starting with `-` is an unknown option. It stops the run before anything is analysed: `Unknown option: <argument>` and the usage line go to stderr and the exit code is 2, so a mistyped flag such as `--stict` fails the build instead of quietly turning a check off.

### Directory and File Filtering

- `--exclude=directory` or `--exclude directory`: Exclude specific directories from analysis
  - Can be used multiple times to exclude multiple directories
  - By default, `vendor/` is excluded
  - Example: `--exclude=tests --exclude=cache`
- `--include-pattern=pattern` or `--include-pattern pattern`: Only analyze files matching the regex pattern
  - Can be used multiple times; a file is analyzed if it matches any of the include patterns
  - Example: `--include-pattern="src/.*\.php$"` to only analyze PHP files in src/
- `--exclude-pattern=pattern` or `--exclude-pattern pattern`: Exclude files matching the regex pattern
  - Can be used multiple times; a file is excluded if it matches any of the exclude patterns
  - Example: `--exclude-pattern="test.*\.php$"` to exclude test files
- Every pattern given is checked before anything is analysed. A pattern that isn't a valid regular expression stops the run: the reason and the usage line go to stderr and the exit code is 2, so a mistyped filter fails the build instead of quietly analysing the wrong files.
  - Example: `--include-pattern="src/("` prints `Invalid --include-pattern: Compilation failed: missing closing parenthesis at offset 6`

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

# Repeat a pattern flag to exclude several things at once
./vendor/bin/explicitness-checker --exclude-pattern=".*Test\.php$" --exclude-pattern="/generated/" ./project
```

**Functional core, imperative shell.** To exempt console commands, controllers or other shells while still checking the core logic they call, in either the CLI or PHPStan, see [docs/functional-core-imperative-shell.md](docs/functional-core-imperative-shell.md).

### Severity Levels and Exit Codes

The tool categorizes violations into three severity levels:

- **Minor** (Exit code 1): Simple output operations
  - `echo`, `print`, `var_dump`, `print_r`
- **Serious** (Exit code 2): Shared state access
  - Global variables (`global`, `$GLOBALS`)
  - Superglobals (`$_GET`, `$_POST`, `$_SESSION`, etc.)
  - Static method calls with no arguments (`ClassName::method()`)
  - Static properties (`ClassName::$property`)
  - Writes through arguments (`$cart[] = $item` or `sort($cart)` with `array &$cart`, `$product->price = 1`)
  - `static` variables and by-reference closure captures (`use (&$x)`)
  - Instance properties (`$this->property`) when `--props` is enabled
- **Critical** (Exit code 3): System-level implicit I/O
  - File operations (`file_get_contents`, `fwrite`, etc.)
  - Environment access (`getenv`, `$_ENV`)
  - Time functions (`time`, `date`, `microtime`)
  - Random functions (`rand`, `random_int`)
  - HTTP headers (`header`, `setcookie`)
  - Session functions (`session_start`, `session_id`)
  - Error logging (`error_log`, `trigger_error`)

**By-reference built-ins.** A built-in that takes an argument by reference, such as `sort`, `array_push` or `preg_match`'s `$matches`, writes to it. Passing it a by-reference parameter, a global, a superglobal, a `static` variable or a by-reference capture is reported as a write. The checker asks the PHP that runs it which parameters are by reference, so the result depends on the extensions loaded there: a call to a function from an extension that isn't loaded isn't reported, and two machines with different extensions can report different results for the same code. User-defined functions, and calls that unpack their arguments (`sort(...$lists)`), are never treated as by-reference.

**Behaviour change:** `$_ENV` access used to be Serious, like the other superglobals. It is now Critical, the same as `getenv`, so a run whose worst finding is `$_ENV` access now exits with 3 instead of 2.

#### Exit Codes for CI Integration

- **0**: No violations found
- **1**: Only minor violations found
- **2**: Serious violations found (may include minor)
- **3**: Critical violations found (may include serious and minor)

The tool exits with the highest severity level found, making it easy to integrate into CI pipelines with appropriate failure thresholds.

If a PHP file cannot be parsed, the tool reports the parse error, continues checking the other files, and exits with at least code 2. A critical violation in another file still raises the exit code to 3.

### Explicitness Percentage Gate

`--min-explicitness=percent` or `--min-explicitness percent` lets a codebase pass while it still has some implicit function-likes, as long as enough of them are explicit. It's meant for adopting the tool gradually: set the minimum to where the codebase is today and raise it over time.

- A function-like is explicit when it has no findings. The percentage is explicit function-likes ÷ checked function-likes × 100, in the current mode, so `--strict` and `--props` can lower it.
- When the percentage is at or above the minimum, violations no longer set the exit code, so the run exits 0. A file that fails to parse still makes it exit at least 2: the gate waives violations, not files it couldn't check. Below the minimum, the run exits with the usual severity code. The `Exit code:` line in the summary shows the code the run actually exits with.
- The comparison is exact: 7 of 8 is 87.5%, which meets `87.5` but not `87.51`. The displayed percentage is rounded down to one decimal place, so 2 of 3 shows as 66.6% and still meets `66.66`.
- A run that checks no function-likes is 100% explicit, so it meets any minimum. Files that fail to read or parse count towards neither total.
- The minimum must be a plain decimal number from 0 to 100, such as `80` or `87.5`. Anything else, including `-5`, `101`, `1e2`, `.5` and an empty value, stops the run: `Invalid --min-explicitness: <value>` and the usage line go to stderr and the exit code is 2.
- If the flag is repeated, the last value wins. Given without a value, it's ignored.

With a minimum set, the summary ends with one more line:

```
  Exit code: 0
  Explicit function-likes: 2 of 3 (66.6%, minimum 50%)
```

On a clean run it follows `No implicit inputs or outputs found.`, without the indent.

### Example Output

```
$ ./vendor/bin/explicitness-checker ./path/to/your/project

Analyzing...

Results:

+---------------------------------------+------+----------+-----------------------------------------------+----------------------------------------------+----------+
| File                                  | Line | Function | Implicit Inputs                               | Implicit Outputs                             | Severity |
+---------------------------------------+------+----------+-----------------------------------------------+----------------------------------------------+----------+
| ./path/to/your/project/Calculator.php | 4    | add      | read from global variable $some_global_number | wrote to global variable $some_global_number | Serious  |
+---------------------------------------+------+----------+-----------------------------------------------+----------------------------------------------+----------+

Summary:
  Critical violations: 0 (exit code 3)
  Serious violations: 1 (exit code 2)
  Minor violations: 0 (exit code 1)
  Exit code: 2

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
| Static method call with no arguments (`ClassName::method()`, not `self::`, `parent::` or `static::`) | `explicitness.staticCall` | default |
| Static property (`ClassName::$property`) | `explicitness.staticProperty` | default |
| Write through a by-reference parameter or an object argument's property | `explicitness.argumentMutation` | default |
| `static` variable (`static $x`) | `explicitness.staticVariable` | default |
| By-reference closure capture (`use (&$x)`) | `explicitness.capturedReference` | default |
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
- `explicitness.staticProperty` overlaps with its opinionated `property.static` identifier, if you've enabled that extension's opinionated rule set.
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
