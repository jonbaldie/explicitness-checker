# Exploratory testing: pipe calls, reference unset, and catalogue direction (2026-10-10)

An AFK exploratory pass over `main` at `68bb322`. It stayed off the surfaces already covered (destructuring keys, dynamic static properties, first-class callable instantiation, nullsafe `$this`, named `fopen` mode) and drove three journeys through the CLI, with the PHPStan extension on the same fixtures where PHPStan could parse them:

- PHP 8.4 property hooks and PHP 8.5 pipe calls, as a user checking code that already runs on this PHP.
- Shared-state writes the README already counts: `unset`, `??=`, `++`, foreach-by-ref, reference destructuring, named by-ref built-ins, and no-argument static calls.
- Strict-mode session and HTTP header built-ins, plus the flag contract after the Options refactor (`--stict` corrected to `--strict`, empty `--exclude`, a missing path).

Six confirmed findings were filed as [#135](https://github.com/jonbaldie/explicitness-checker/issues/135)–[#140](https://github.com/jonbaldie/explicitness-checker/issues/140).

## Environment

| | |
|---|---|
| Repo | `main` at `68bb322` |
| CLI / Extension | `bin/explicitness-checker` vs `extension.neon` |
| PHP / PHPStan / php-parser | 8.5.9 / 2.2.14 / v5.9.0 |
| Modes | Default, `--strict`, `--strict --props` |
| Fixtures | Scratch files under the run `TMPDIR`, not committed |

Dependencies came from `composer install --no-interaction` against `composer.lock`. No Docker resources were created.

## Journeys exercised

### 1. Check PHP 8.4 hooks and PHP 8.5 pipe calls

Goal: a file this PHP runs is analysed, and a pipe that calls a catalogued built-in is the same strict-mode finding as the direct call. README naming for property hooks was the other expectation.

Ordinary path: block and short `get`/`set` hooks, a promoted-parameter hook, a trait hook, an anonymous-class hook, and an abstract interface hook with no body. Variation: pipe into `unlink(...)`, `file_get_contents(...)`, and `exit(...)`, then the same built-in stored rather than called, then an immediately invoked callable.

Hooks matched the README. Short `set => $value + time()` reported `reads system time (time)` under `--strict`. The abstract hook had no row. The interface and anonymous-class names matched the documented forms.

The pipe variation failed. `$path |> unlink(...)` deletes the file and has no row; `unlink($path)` in the same file is Critical and exits 3. `$code |> exit(...)` with `$code = 7` ended the process with status 7 and was not reported. Filed as [#135](https://github.com/jonbaldie/explicitness-checker/issues/135).

Calling the same built-in as `(unlink(...))($path)`, `('unlink')($path)` or `call_user_func('unlink', $path)` also deletes or is a direct call, and also has no row. Filed as [#140](https://github.com/jonbaldie/explicitness-checker/issues/140). `return unlink(...)` correctly had no row.

### 2. Shared-state writes the README already counts

Goal: a write that changes the caller's data, a global, a static, or a superglobal is reported; a local rebinding that does not change that data is not.

Ordinary path: `unset` of a by-ref element, an object property, a superglobal and `$GLOBALS['cart']`; `??=`, `++` and `+=` on a by-ref parameter or an object property; foreach-by-ref; `[&$first] = $cart`; `sort(array: $cart)`; `preg_match(..., matches: $matches)`. Those reported the write the README describes. `sort(...$lists)` stayed unreported, as documented.

Variation: `unset` of the reference symbol itself, and a no-argument static call whose class or method name is an expression.

`unset($cart)` on `array &$cart`, `global $g; unset($g)`, `unset` of a static, and `unset` of an alias to `$GLOBALS['cart']` were all reported as writes. PHP 8.5.9 left the shared value unchanged in each case. Filed as [#136](https://github.com/jonbaldie/explicitness-checker/issues/136). `unset($_GET['a'])` and `unset($GLOBALS['cart'])` do change the value and are reported; those are not the bug.

`Clock::now()` was reported. `$class::now()`, `($class)::now()` and `Clock::{$name}()` were not, including when the class is a literal and only the method name is an expression. Filed as [#137](https://github.com/jonbaldie/explicitness-checker/issues/137). `$class::$n = 1` was reported, so the gap is the call, not dynamic class syntax in general.

`catch (RuntimeException $error)` on a by-ref parameter does replace the caller's value. Reporting that write is right.

### 3. Strict session and header findings, and a corrected flag

Goal: session and HTTP header calls land in the input or output column that matches what PHP does, and a mistyped flag fails before analysis so a corrected rerun can be trusted.

Ordinary path: `session_id()`, `session_id('abc')`, `session_id(id: 'abc')`, `session_name('APP')`, `http_response_code()` and `http_response_code(201)`, then the session and header neighbours of the functions the catalogue already names.

`session_id('abc')` and `session_name('APP')` change the id and the name, and are reported only as reads. `http_response_code()` does not set a code, and is reported as a header write. Filed as [#138](https://github.com/jonbaldie/explicitness-checker/issues/138). `session_id()` and `session_id(null)` are reads and were reported as reads.

`header_remove()`, `headers_list()`, `headers_sent()`, `apache_response_headers()`, `header_register_callback(...)`, `session_save_path('/tmp')`, `session_module_name('files')`, `session_cache_limiter('nocache')`, `session_cache_expire(10)`, `session_abort()`, `session_reset()`, `session_status()` and `session_create_id()` produced no row. A file of only those functions exits 0. `session_save_path('/tmp')` left the path `/tmp`. Filed as [#139](https://github.com/jonbaldie/explicitness-checker/issues/139).

Flag variation: `--stict` printed `Unknown option: --stict` and the usage line, exit 2, and did not analyse. The corrected `--strict` run analysed. `--exclude=` with an empty name exited 2 with `Invalid --exclude: directory name is empty` before analysis, including when `--min-explicitness=0` was also present. A missing path exited 2 with `Path not found`. `--min-explicitness=100` on the session fixture still exited 3 and printed the percentage line.

The PHPStan extension, on the fixtures it could parse, reported the same finding texts as the CLI for the unset, static-call, session and hook cases. It did not disagree.

## Confirmed findings (filed)

| # | Where | Impact | Issue |
|---|---|---|---|
| 1 | Strict catalogue / first-class callable skip | `$path \|> unlink(...)` and `$code \|> exit(...)` run the built-in and are not reported, so a pipe-only file exits 0 | [#135](https://github.com/jonbaldie/explicitness-checker/issues/135) |
| 2 | `UnsetRule` | `unset` of a by-ref parameter, a declared global, a static, or a `$GLOBALS` alias is reported as a write. PHP only destroys the local symbol. Exit 2 | [#136](https://github.com/jonbaldie/explicitness-checker/issues/136) |
| 3 | `StaticCallDetector` | `Clock::now()` is reported. `$class::now()`, `($class)::now()` and `Clock::{$name}()` are not. Exit 0 | [#137](https://github.com/jonbaldie/explicitness-checker/issues/137) |
| 4 | `BuiltinCatalogueDetector` | `session_id('abc')` and `session_name('APP')` are inputs only. `http_response_code()` with no argument is reported as a header write | [#138](https://github.com/jonbaldie/explicitness-checker/issues/138) |
| 5 | Strict catalogue | `header_remove`, `headers_list`, `headers_sent`, `apache_response_headers` and the `session_save_path` / `session_module_name` / `session_cache_*` / `session_abort` / `session_reset` / `session_status` / `session_create_id` family are silent. Exit 0 | [#139](https://github.com/jonbaldie/explicitness-checker/issues/139) |
| 6 | Strict catalogue | `(unlink(...))($path)`, `('unlink')($path)` and `call_user_func('unlink', $path)` are not reported. The direct call is. Exit 0 | [#140](https://github.com/jonbaldie/explicitness-checker/issues/140) |

Each miss or false write above was run twice from a known file, once inside the first fixture directory and again from a clean replay file. The PHPStan extension matched the CLI on [#136](https://github.com/jonbaldie/explicitness-checker/issues/136), [#137](https://github.com/jonbaldie/explicitness-checker/issues/137) and [#138](https://github.com/jonbaldie/explicitness-checker/issues/138).

## Rejected candidates

- **Short property hooks crash, or skip implicit I/O.** Block and short hooks are checked under the README names. `set => $value + time()` reports `time` in strict mode. An abstract hook with no body is not a row. The synthetic `$this->prop` write on a short `set` hook appears only under `--props`, same as a block hook that assigns `$this->prop`.
- **`return unlink(...)` reported as a call.** No row. The #107 exemption holds for a callable that is only stored.
- **`array_map(unlink(...), $paths)` and a variable callee `$fn($path)`.** No row. The checker does not follow callbacks or dataflow. Not filed. `call_user_func('unlink', $path)` was filed, because the callee is a literal and the call happens in the function.
- **`catch (E $param)` on a by-ref parameter.** PHP replaces the caller's value with the exception. The write finding is right.
- **`print_r($value, 1)` reported as stdout.** On PHP 8.5 the call throws `TypeError` before printing. The documented exemption is `return: true`. Not a checker defect against that sentence.
- **`error_reporting(null)` reported as a write.** It reads the current level. Already rejected on 2026-10-02: `isEmpty()` is the intentional split, not `omits()`.
- **`ob_get_contents` labelled as stdout.** Already rejected on 2026-09-25.
- **`fopen(...$args)` reported as a read.** Matches the catalogue rule that a non-literal mode is treated as read.
- **`@echo` parse error.** Invalid PHP. `@print` of a superglobal is parsed and reported.
- **`__halt_compiler()` after a function.** PHP accepts the file. The checker analyses the function and does not fail the run.
- **`new DateTime` and `new DateTime()`.** Both report a clock read in strict mode, as documented.
- **`isset` / `empty` / `match` on superglobals, and `@print`.** Reported as reads. `@print` also reports stdout.
- **Nullsafe reads of an argument property.** No finding. Reads of arguments are not implicit.
- **Reference binding `$alias = &$g` after `global $g`.** Reported read and write. That is the documented conservative binding, not an extra unset finding.

## Usability observations

- A second path is dropped with no stderr note. `explicitness-checker proj/src proj/other` analysed only `proj/src` and exited 2 for the global in that directory. `proj/other`, which reads `$_GET`, was not checked. The usage line shows one path, and the parser keeps the first path on purpose. The silence is what makes the run look complete.
- Correcting `--stict` to `--strict` works: the typo exits 2 with the usage line and does not analyse; the corrected command analyses. Empty `--exclude` and a missing path do the same.

## Not exercised

- PHP versions other than 8.5.9. By-reference signatures remain environment-dependent by design.
- Transitive effects across a call the checker does not already treat as a literal callee. `array_map` was probed only far enough to reject it.
- Framework install flows.
- PHPUnit, PHPStan level max, PHPMD and Infection were not re-run as a baseline. The checker binary from this checkout ran.

## Limitations

- PHPStan 2.2.14 reports `Syntax error, unexpected '>'` on `|>` and stops the run before the extension can see those functions. Pipe parity is CLI-only for that reason. The other confirmed findings were compared and matched.
- The PHPStan probe did not set `phpVersion`. PHPStan then warned that property hooks need PHP 8.4. The extension still emitted hook findings. That warning is PHPStan's, not an extension miss.
- No containers, images or volumes were created. Scratch fixtures stayed in `TMPDIR`.
