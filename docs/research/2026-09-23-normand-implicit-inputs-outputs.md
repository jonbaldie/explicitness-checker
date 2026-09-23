# Implicit inputs and outputs: Normand's definition vs the checker

Research note, 2026-09-23. Compares Eric Normand's definition of implicit inputs and outputs in *Grokking Simplicity* with what `bin/explicitness-checker` detects on `origin/main` at `963de64`.

## Summary

Normand's definition is total: "The explicit inputs are the arguments. The explicit output is the return value. Any other way information enters or leaves the function is implicit." (book L4820–4821). The checker implements a fixed list of syntactic patterns. By default it detects globals, superglobals, `$GLOBALS` and argumentless static calls. `--strict` adds a catalogue of 43 built-in functions plus `echo`/`print`/`exit`, and `--props` adds `$this->x` and `Class::$x`.

The biggest gaps, measured against the book:

- **Mutating arguments.** Normand's own worked example of an implicit output is `cart.push(...)` on an argument (L5282–5283). No PHP equivalent is reported in any mode.
- **Shared mutable state outside `global`.** Static variables and by-reference closure captures are not reported in any mode. Static properties are reported only under `--props`.
- **The book's headline actions.** Sending email, querying a database and making web requests (L1395, L2577, L4286–4289, L5459–5462) are not in the catalogue. Printing, time and randomness (L4829, L4217, L1413) are detected only with `--strict`.
- **"Actions spread"** (L4169–4171). The checker looks at one function at a time and doesn't follow calls.

It also reports a few things that Normand's definition treats as explicit or as calculations (see "False positives").

Every "not reported" claim below was checked by running the checker. The commands and full outputs are under "Verification" at the end.

## Checks we don't do but should (ranked)

Ranked by value: how central the gap is to Normand's definition × how feasible the check is.

| # | Gap | In/Out | Feasibility | FP risk | Suggested mode / category |
|---|---|---|---|---|---|
| 1 | Mutating arguments: writes through `&$param`, by-reference built-ins (`sort($p)`), `$param->x = …`, `unset($param->x)` | Output | Syntactic (built-in by-ref signatures through runtime `ReflectionFunction`) | Low | default / `argumentMutation` |
| 2 | Static properties (`Class::$x`) in default mode | In + Out | Already implemented (props-only) | Low | default / `staticProperty` (move) |
| 3 | Static variables (`static $n`) | In + Out | Syntactic | Low (memoisation is a judgement call) | default / `staticVariable` |
| 4 | By-reference closure captures (`use (&$x)`) | In + Out | Syntactic | Low | default / `capturedReference` |
| 5 | Printing, time and randomness only under `--strict` | In + Out | Already implemented | Low–medium (see false positives) | policy decision: promote to default or document why not |
| 6 | Network, database, shell, mail, `include`/`require`, `filter_input`, runtime config (procedural functions) | In + Out | Syntactic (name catalogue) | Low | strict / `network`, `database`, `process`, `mail`, `include`, `runtimeConfig`; `filter_input` → `superglobal` |
| 7 | Missing names in existing strict categories (`new DateTimeImmutable()`, `hrtime`, `gmdate`, `shuffle`, `array_rand`, `uniqid`, `fgets`, `file`, `fputs`, `unlink`, `fprintf(STDOUT, …)`, …) | In + Out | Syntactic | Low | strict / existing categories |
| 8 | Transitivity: calling a function that is itself an action | In + Out | Two-pass syntactic call graph for functions and `$this`/`self::` methods; needs types for other methods | Medium (noisy by nature) | new flag `--transitive` / `callsAction` |
| 9 | I/O through objects: `$pdo->query()`, `Cache::get($k)`, `$logger->info()` | In + Out | Needs type info (PHPStan scope) plus a class/method catalogue | Medium | PHPStan-only, strict / reuse `database`, `network`, … |
| 10 | Handing out a mutable reference: `function &f()`, returning an internal mutable object | Output | `&` return is syntactic; the rest isn't feasible | Low for `&` | strict / `referenceReturn` |
| 11 | Reading a mutable object argument (`$user->first_name`) | Input | Needs type info (readonly/immutable detection) | High | new flag, if at all / `mutableArgument` |

## Detailed entries

Book references are line numbers in a plain-text export of the book, not page numbers. Tool references are `origin/main:path:line`. The "not reported" claims cite the verification files `gaps.php`, `fp.php` and `flags.php`, which are reproduced at the end with their outputs.

### 1. Mutating arguments

- **Normand.** Worked example: `add_item(cart, name, price) { cart.push(...) }`. Although `cart` is an argument, "we are still modifying the global array by calling .push(), which is an implicit output" (L5282–5283). Outputs include "modifying a shared object" (L5461–5462). He also asks: "Would this still be a calculation if we modified the array passed as an argument?" (L5355–5356). `delete user.first_name` is an action because "deleting a property can affect other parts of the code" (L4241–4243).
- **PHP translation.** PHP arrays and scalars passed by value are copies, so the JS array case applies only to (a) by-reference parameters, (b) by-reference built-ins applied to them, and (c) objects, whose handles are shared.
- **Not reported** (`gaps.php`, all four modes):
  ```php
  function add_item(array &$cart, string $name) { $cart[] = $name; }            // L7
  function sort_items(array &$items) { sort($items); }                          // L8
  function set_price(\stdClass $item, int $price) { $item->price = $price; }    // L10
  function drop_name(\stdClass $user) { unset($user->first_name); }             // L11
  function append_item(\ArrayObject $cart, string $name) { $cart->append($name); } // L12
  ```
  Related partial gap: `function sort_global() { global $list; sort($list); }` (L9) is reported only as "read from global variable $list". The write that `sort` makes through its by-reference parameter is missed.
- **Why.** `origin/main:src/Detect/VariableDetector.php:63-65` returns early for every parameter, by-reference or not. `ObjectPropertyDetector` only matches `$this` (`origin/main:src/Detect/ObjectPropertyDetector.php:19-24`). No walk rule treats a by-reference argument position as a write (`origin/main:src/Walk/AccessRules.php:20-31`).
- **Feasibility.**
  - (a) Purely syntactic. `Param::$byRef` is on the AST; report writes to that parameter.
  - (b) Built-in by-reference positions are available at runtime: `(new ReflectionFunction('sort'))->getParameters()[0]->isPassedByReference()` returns true, as it does for `shuffle`, `array_push`, `end` and `preg_match` arg 3 (verified with `php -r`). User functions in the same file resolve syntactically; methods need types.
  - (c) Property writes and `unset` on a parameter are syntactic.
  - Mutating method calls (`$cart->append()`) need type info and a list of mutating methods, so they aren't feasible in general.
- **FP risk.** Low for (a)–(c). A by-reference parameter exists to be written.
- **Suggested.** Default mode, category `argumentMutation`, reported as an output.

### 2. Static properties outside `--props`

- **Normand.** "reading a global is an implicit input", "modifying a global is an implicit output" (L4825, L4832). "Shared variables (such as globals) are common implicit inputs and outputs." (L5915)
- **PHP translation.** `Class::$x` is process-wide mutable state, which makes it a global variable under another name. PHP doesn't allow `readonly` on static properties, so every one of them is mutable.
- **Not reported in default or `--strict`:** `class Counter { public static int $n = 0; } function bump() { return ++Counter::$n; }` (`gaps.php:18-19`). `--props` reports it (`gaps.php` props output, row L19).
- **Why.** `StaticPropertyDetector` is only added when `$mode->isProps()` is on (`origin/main:src/Detect/DetectorSet.php:38-41`).
- **Feasibility.** Already implemented. Moving it to default is a one-line change in `DetectorSet`. `$this->x` is a different case (see Ambiguities).
- **FP risk.** Low. Static properties used as constant tables could be written as class constants instead.
- **Suggested.** Default mode, existing category `staticProperty`. Behaviour change: exit codes rise for existing users.

### 3. Static variables

- **Normand.** An action is "Anything that depends on when it is run, or how many times it is run" (L1610–1611). Inputs are "anything that can affect the result between calls to the function" (L5456–5457). "writing to a shared, mutable variable is an action" (L4231–4232).
- **PHP translation.** `static $id` persists between calls, so the result depends on how many times the function has run. It is a global scoped to one function.
- **Not reported** (any mode): `function next_id() { static $id = 0; return ++$id; }` (`gaps.php:15`).
- **Why.** No detector matches `Stmt\Static_`. `VariableDetector` only knows parameters, `global` names and superglobals (`origin/main:src/Detect/VariableDetector.php:59-75`).
- **Feasibility.** Purely syntactic. Collect `Stmt\Static_` names the way `GlobalDeclarations` collects `global` names (`origin/main:src/Walk/GlobalDeclarations.php`), then report reads and writes of them.
- **FP risk.** Low. Memoising a pure calculation is the arguable case (see Ambiguities).
- **Suggested.** Default mode, category `staticVariable`.

### 4. By-reference closure captures

- **Normand.** "if y is a shared, mutable variable, reading it can be different at different times" (L4219). "writing to a shared, mutable variable is an action because it can affect other parts of the code" (L4231–4232).
- **Not reported** (any mode): `function make_counter() { $n = 0; return function () use (&$n) { return ++$n; }; }` (`gaps.php:22`). The `{closure}` has no row.
- **Why.** Closures are checked on their own (`origin/main:src/Scope/ScopeBoundary.php`). `Analyser::parameterNames` collects only `getParams()`, not `use` variables (`origin/main:src/Analyser.php:50-61`), and `VariableDetector` ignores names that aren't `global` or superglobal.
- **Feasibility.** Syntactic. `Expr\ClosureUse::$byRef` is on the AST.
- **FP risk.** Low.
- **Suggested.** Default mode, category `capturedReference`. By-value `use ($x)` and arrow functions are not proposed (see Ambiguities).

### 5. Printing, time and randomness are strict-only

- **Normand.** Printing is his first example of an implicit output: "printing is an implicit output" for `console.log` (L4828–4829). `new Date()` "makes a different value depending on when you call it" (L4217–4218). `getCurrentTime()`: "each time you call it, you get a different time" (L1413–1417). The book only mentions `Math.random` in a timing example (L22936), so treating randomness as an input rests on his general test "Does it depend on when or how many times it runs?" (L4249).
- **Not reported in default mode:** `echo`, `time()` and `random_int()` in `flags.php` give "No implicit inputs or outputs found." and exit 0. With `--strict`, all three are reported and the run exits 3.
- **Why.** `LanguageConstructDetector`, `ExitDetector` and `FunctionCallDetector` are strict-only (`origin/main:src/Detect/DetectorSet.php:33-37`).
- **Feasibility.** Already implemented.
- **FP risk.** Low to medium. The strict catalogue has false positives (see below) that should be fixed before any promotion.
- **Suggested.** This is a policy decision. Normand's definition doesn't grade implicit I/O, so a default run that misses his headline examples undercounts by his measure. Either promote the categories or state in the README that default mode is a deliberate subset.

### 6. Network, database, process, mail, include, config

- **Normand.** Example inputs include "queries of a database" (L5459–5460). Example outputs include "sending a web request" (L5462). His list of actions: "Sending an email", "Sending an ajax request" (L4286–4289). "read from a database" (L2577–2578). The database "is mutable, so access to it is an action" (L31544).
- **Not reported** (any mode; `gaps.php:25-32`):
  ```php
  function http_get(string $u) { $c = curl_init($u); return curl_exec($c); }
  function db_query($link) { return mysqli_query($link, 'SELECT 1'); }
  function run_cmd(string $c) { return shell_exec($c); }
  function run_ls() { return `ls`; }
  function send_mail(string $to) { return mail($to, 'Subject', 'Body'); }
  function load_config(string $p) { return require $p; }
  function query_param() { return filter_input(INPUT_GET, 'q'); }
  function set_tz() { date_default_timezone_set('UTC'); }
  ```
- **Why.** None of these are in `FunctionCallDetector::CATALOGUE` (`origin/main:src/Detect/FunctionCallDetector.php:39-83`). Backticks (`Expr\ShellExec`) and `Expr\Include_` have no detector.
- **Feasibility.** Syntactic: name catalogue plus two node types. Candidates:
  - **network:** `curl_exec`, `curl_multi_exec`, `fsockopen`, `stream_socket_client`, `gethostbyname`, `dns_get_record`.
  - **database:** `mysqli_query`, `mysqli_*`, `pg_query`, `pg_*`, `sqlite_*`.
  - **process:** `exec`, `shell_exec`, `system`, `passthru`, `proc_open`, `popen`, backticks, `sleep`/`usleep` (arguable).
  - **mail:** `mail`, `mb_send_mail`.
  - **errorLog:** `syslog`.
  - **include:** `include`, `require` and the `_once` forms.
  - **runtimeConfig:** `ini_set`/`ini_get`, `set_error_handler`, `set_exception_handler`, `date_default_timezone_set`/`_get`, `setlocale`, `define`, `error_reporting`, `register_shutdown_function`.
  - **superglobal:** `filter_input` and `filter_input_array` read `$_GET` and the other superglobals.
- **Limit.** Modern PHP does most of this through objects (PDO, HTTP clients, mailers), which is gap 9.
- **FP risk.** Low.
- **Suggested.** Strict. New categories `network`, `database`, `process`, `mail`, `include`, `runtimeConfig`. Put `syslog` under `errorLog` and `filter_input` under `superglobal`.

### 7. Missing names in existing strict categories

- **Normand.** As for gap 5 (L4217–4218, L1413–1417, L4249, L4829).
- **Not reported** (any mode; `gaps.php:35-45`): `new \DateTimeImmutable()`, `hrtime(true)`, `gmdate('Y-m-d')`, `shuffle($xs)`, `array_rand($xs)`, `uniqid()`, `fgets($h)`, `file($p)`, `fputs($h, $s)`, `unlink($p)`, `fprintf(STDOUT, …)`.
- **Why.** The catalogue is a closed list (`origin/main:src/Detect/FunctionCallDetector.php:39-83`). `new` expressions are not inspected at all. `FILE_SYSTEM` has only read entries, so writes such as `unlink`, `rename`, `mkdir`, `rmdir`, `copy`, `touch` and `chmod` are unmapped.
- **Feasibility.** Syntactic. For `new DateTime*()` and `date_create()`, report only when there's no argument or the argument is a literal relative time such as `'now'`, to keep false positives low. Other candidates:
  - **time:** `mktime()` with no arguments, `strtotime` with a relative string, `getdate()`, `localtime()`, `idate`, `date_create`.
  - **random:** `str_shuffle`, `lcg_value`, `Random\Randomizer`.
  - **file:** `fgetc`, `fgetcsv`, `fscanf`, `fputcsv`, `stream_get_contents`, `readfile`, `fpassthru`, `tmpfile`, `tempnam`.
  - **stdout:** `ob_*`, `flush`.
- **FP risk.** Low.
- **Suggested.** Strict, existing categories. File-system writes should be outputs, which `FILE_SYSTEM` doesn't have yet.

### 8. Transitivity ("actions spread")

- **Normand.** "If you call an action in a function, that function becomes an action. If you call that function in another function, it becomes an action. One little action somewhere and it spreads all over." (L4169–4171). The affiliate-payout walkthrough traces this spread through the call chain (L3999–4075).
- **Not reported** (any mode): `function calls_action() { return count(reads_global()); }` (`gaps.php:49`). Only `reads_global` (L48) has a row.
- **Why.** `Analyser` analyses one function-like at a time (`origin/main:src/Analyser.php:26-45`) and doesn't build a call graph.
- **Feasibility.**
  - Namespaced function calls are resolved by NameResolver, so a project-wide two-pass analysis can propagate findings along calls to functions, `self::`/`static::` methods and `$this->method()`.
  - Other method calls need types, which PHPStan's `Scope` provides. The rule currently ignores `$scope` and calls the syntactic `Analyser` (`origin/main:src/PHPStan/ImplicitInputOutputRule.php`).
  - Calls to a callable parameter must not propagate: Normand treats a passed-in callback as the explicit output channel (L25250–25252, L25326–25328).
- **FP risk.** Medium. There are no false positives by Normand's definition, but every caller of the shell gets flagged. It needs its own flag and a clear "via `f()`" description.
- **Suggested.** New flag `--transitive`, category `callsAction`.

### 9. I/O through objects and argumented static calls

- **Normand.** As for gap 6.
- **Not reported** (any mode; `gaps.php:52-53`): `$pdo->query('SELECT 1')` and `\Cache::get($k)`. The README already names the facade case as a blind spot (`origin/main:README.md:77`).
- **Feasibility.** Needs type info, so it could only be done in the PHPStan path, together with a catalogue of impure classes and methods. PHPStan's own purity metadata is not a drop-in replacement. It follows a different idea of "side effects": its bundled function metadata marks `'array_rand' => ['hasSideEffects' => false]` but `'date' => ['hasSideEffects' => true]` (found by grepping `vendor/phpstan/phpstan/phpstan.phar`). By Normand's test, `array_rand` reads an implicit input.
- **FP risk.** Medium.
- **Suggested.** PHPStan-only, strict, reusing the categories from gap 6.

### 10. Handing out a mutable reference

- **Normand.** "If we return a value and some piece of our function later changes it, that's a kind of implicit output." (L5469–5470). "Any data that leaves the safe zone is potentially mutable." (L9087)
- **Not reported:** `public function &items(): array { return $this->items; }` (`gaps.php:59`). `--props` reports only the `$this->items` read, not the by-reference return.
- **Feasibility.** `ClassMethod::$byRef` and `Function_::$byRef` are syntactic. Returning an internal mutable object that the function keeps using isn't decidable statically.
- **FP risk.** Low for `&`.
- **Suggested.** Strict, category `referenceReturn`. Low priority.

### 11. Reading a mutable object argument

- **Normand.** "if user is a shared, mutable object, reading first_name could be different each time" (L4221–4222). "Reads to mutable data are actions" (L8278–8280). "if something changes the argument values after our function has received them, that is a kind of implicit input" (L5470–5471).
- **Not reported:** `function first_name(\stdClass $user) { return $user->first_name; }` (`gaps.php:56`).
- **Feasibility.** Needs type info to tell mutable objects from readonly or immutable ones. Normand's condition is that something *else* changes the object, which is a whole-program property.
- **FP risk.** High. Most object arguments would be flagged.
- **Suggested.** Leave out of the default and strict modes. Consider it only as an opt-in flag. Listed for completeness.

## False positives by Normand's definition

Verified with `fp.php` (outputs below).

| Case | Reported as | Why it's explicit or a calculation by Normand | Evidence |
|---|---|---|---|
| `print_r($x, true)`, `var_export($x, true)` | `writes to standard output`, `--strict` | With `true` they return a string and print nothing. The output is the return value, which is explicit (L4820–4821). | `fp.php:11-12`. Catalogue maps both unconditionally: `origin/main:src/Detect/FunctionCallDetector.php:43-44` |
| `date('Y-m-d', $ts)` | `reads system time (date)`, `--strict` | With a timestamp argument it doesn't read the clock. It does still read the default timezone, which is a different implicit input, so it is mislabelled rather than wrongly flagged. | `fp.php:13`. `origin/main:src/Detect/FunctionCallDetector.php:52` |
| `Money::zero()`, `Status::cases()` | `read from static method`, default | An argumentless call that always returns the same value is a calculation. "Reads to immutable data structures are calculations" (L8289–8290). | `fp.php:14-15`. `origin/main:src/Detect/StaticCallDetector.php:21-32` matches any argumentless static call |
| `$this->x` where `x` is `readonly` | `read from object property`, `--props` | The data is immutable once constructed (L8289–8290). This is a false positive only if `$this` counts as an argument (see Ambiguities). | `fp.php:8`. `origin/main:src/Detect/ObjectPropertyDetector.php:19-29` doesn't check `readonly` |

## Coverage table

"Default", "strict" and "props" are the CLI modes. `--strict --props` is the union of the two, because `DetectorSet` adds the detector groups independently (`origin/main:src/Detect/DetectorSet.php:28-41`).

| Normand's category (book line) | PHP equivalent | Status | Where |
|---|---|---|---|
| Arguments are explicit inputs (L4820) | parameters | Consistent (never reported) | `VariableDetector.php:63-65` |
| Return value is explicit output (L4835) | `return` | Consistent | — |
| Final callback is the explicit output in async code (L25326–25328) | calling a callable parameter | Consistent (not reported) | — |
| Reading a global (L4825) | `global $x`, `$GLOBALS[...]`, superglobals | Covered, default | `VariableDetector.php:67-75`, `GlobalsArrayDetector.php` |
| Modifying a global (L4832, L4288) | writes to the above | Covered, default. Partly: writes through by-reference built-ins (`sort($g)`) are reported as reads | `gaps.php:9` |
| Printing (L4829, L4200) | `echo`, `print`, `printf`, `var_dump`, `print_r`, `exit 'msg'` | Strict only | `LanguageConstructDetector.php`, `ExitDetector.php`, `FunctionCallDetector.php:40-44` |
| Printing via other functions | `fprintf(STDOUT)`, `fputs`, `readfile`, `fpassthru`, `ob_*` | Not covered | `gaps.php:44-45` |
| `alert`/DOM updates (L4193, L4883) | HTTP headers, cookies | Strict only | `FunctionCallDetector.php:70-73` |
| Current time (L4217, L1413) | `time`, `date`, `microtime`, `gettimeofday` | Strict only | `FunctionCallDetector.php:51-54` |
| Current time, other forms | `new DateTime*()`, `date_create`, `hrtime`, `gmdate`, `mktime()` | Not covered | `gaps.php:35-37` |
| Randomness (L4249 test) | `rand`, `mt_rand`, `random_int`, `random_bytes`, `srand` | Strict only | `FunctionCallDetector.php:55-60` |
| Randomness, other forms | `shuffle`, `array_rand`, `str_shuffle`, `uniqid`, `Randomizer` | Not covered | `gaps.php:38-40` |
| Database queries (L5459, L2577, L31544) | `mysqli_*`, `pg_*`, PDO | Not covered | `gaps.php:26,52` |
| Web request / ajax (L5462, L4289) | `curl_exec`, `fsockopen`; `fopen`/`file_get_contents` on a URL | Not covered; URL reads show up only as `file`, strict | `gaps.php:25` |
| Sending email (L1395, L4286) | `mail()` | Not covered | `gaps.php:29` |
| Reading from outside the function (L5459) | `getenv`, `$_ENV`, file reads, file-system checks, session | Covered: `getenv`, file reads, file system and session are strict; `$_ENV` is default | `FunctionCallDetector.php:47-49,61-69,77-82` |
| Modifying a shared object / argument (L5461–5462, L5282–5283) | `&$param` writes, `$param->x =`, `unset($param->x)` | Not covered | `gaps.php:7-12` |
| Shared mutable variable (L4219, L4231) | `static $x` | Not covered | `gaps.php:15` |
| Shared mutable variable | `use (&$x)` | Not covered | `gaps.php:22` |
| Shared mutable variable | `Class::$x` | Props only | `DetectorSet.php:38-41` |
| Shared mutable object property (L4221) | `$this->x` | Props only | `ObjectPropertyDetector.php` |
| Shared mutable object property | `$arg->x` | Not covered | `gaps.php:56` |
| Shared mutable array element (L4226) | `$g[0]` on a global | Covered, default (as a read of the global) | `ArrayIndexRule` |
| Property deletion (L4241–4243) | `unset($g)`, `unset($this->x)` | Covered as a write (issue #10); `unset($param->x)` not covered | `gaps.php:11` |
| Actions spread (L4169–4171) | calls to functions that have findings | Not covered | `gaps.php:49` |
| Returned value mutated later (L5469–5470) | `function &f()` | Not covered | `gaps.php:59` |
| Argument mutated after receipt (L5470–5471, L9087–9089) | shared object handles | Not covered (infeasible in general) | — |
| Reads of immutable data are calculations (L8289–8290) | class constants, `define`d constants | Consistent (not reported); readonly `$this->x` is reported under props | `fp.php:8` |
| — (PHP-specific) | argumentless static calls | Covered, default (with false positives) | `StaticCallDetector.php` |
| — (PHP-specific) | process: shell, `include`, runtime config | Not covered | `gaps.php:27-32` |

(Paths in the "Where" column are under `origin/main:src/Detect/` unless they name a verification file.)

## Ambiguities and judgement calls

- **Is `$this` an argument?** Normand's examples are free JS functions, so he never says. `$this` is passed implicitly, as a hidden argument. `--props` treats every `$this->x` as implicit. A reading closer to Normand would flag only mutable properties: reads of a mutable property are "actions" (L8278–8280), and reads of immutable data are "calculations" (L8289–8290). Either reading is defensible.
- **Reading object arguments.** Arguments are explicit (L4820), yet "if something changes the argument values after our function has received them, that is a kind of implicit input" (L5470–5471). Whether that happens depends on the caller, not the function. The book resolves it by discipline (copy-on-write, defensive copying, ch. 6–7), not by classification.
- **PHP arrays aren't JS arrays.** Normand's mutating-argument example relies on JS arrays being shared references. PHP arrays and strings passed by value are copied on write, so they carry no implicit output. Mapping his concern onto `&$param` and objects is my translation, not his statement.
- **By-value captures.** `use ($x)` and arrow functions capture a snapshot when the closure is created. That snapshot could be read as a bound argument (explicit) or as "any other input" (L4846–4852). The book discusses neither.
- **Memoising static variables.** A `static $cache` that memoises a pure calculation doesn't change the result between calls, so it passes Normand's "when or how many times" test (L4249), even though it is shared mutable state. His note that local mutation during initialisation is fine (L5435–5436) doesn't cover state that survives across calls.
- **Exceptions.** The book never classifies `throw` as an implicit output. I found nothing on it when grepping for "throw" and "exception".
- **Ambient configuration.** Many built-ins read process-wide settings: `date()` reads the default timezone, and others depend on the locale or `ini` values. Strictly, every such call has an implicit input. Flagging them all would be noisy, so where to draw the line is a judgement call.
- **`exit`/`die`.** Terminating the process is clearly an effect. The tool files it under `standardOutput` ("terminates the program", `origin/main:src/Detect/ExitDetector.php:37-40`), which is a labelling choice rather than a Normand category.
- **Severity.** Normand doesn't rank implicit inputs and outputs ("Any other input", "Any other output", L4846–4852). The tool's Minor/Serious/Critical scale and the default/strict split are the tool's own design choices.

## README vs source disagreements

The source is authoritative.

1. The README's `--strict` flag list (`origin/main:README.md:98-100`) names only file I/O and standard output. The code also detects environment, time, random, file-system checks, headers, error log and session (`origin/main:src/Detect/FunctionCallDetector.php:39-83`), plus `exit`/`die` (`ExitDetector.php`). The README's PHPStan category table lists all of these except `exit`/`die`.
2. The README never mentions `exit`/`die` or the `fopen` mode handling (`FunctionCallDetector.php:114-138`).
3. The severity section (`README.md:143-163`) lists echo, time, random and similar calls without saying that they are reported only under `--strict`.

## Verification

Environment:
- Worktree: `git worktree add /tmp/ec-origin origin/main`, at `963de64`.
- Setup: `composer install`, PHP 8.4.1.
- Command: `php bin/explicitness-checker [flags] <file>`.
- Output below keeps only the table rows.

`/tmp/ec-scratch/gaps.php`:

```php
<?php
namespace Scratch;

function control() { global $total; return $total; }                      // control: must be reported

// G1 argument mutation
function add_item(array &$cart, string $name) { $cart[] = $name; }
function sort_items(array &$items) { sort($items); }
function sort_global() { global $list; sort($list); }                     // write missed, read reported
function set_price(\stdClass $item, int $price) { $item->price = $price; }
function drop_name(\stdClass $user) { unset($user->first_name); }
function append_item(\ArrayObject $cart, string $name) { $cart->append($name); }

// G2 static variable
function next_id() { static $id = 0; return ++$id; }

// G3 static property (props-only today)
class Counter { public static int $n = 0; }
function bump() { return ++Counter::$n; }

// G4 closure capturing by reference
function make_counter() { $n = 0; return function () use (&$n) { return ++$n; }; }

// G5 network, database, process, mail, include
function http_get(string $u) { $c = curl_init($u); return curl_exec($c); }
function db_query($link) { return mysqli_query($link, 'SELECT 1'); }
function run_cmd(string $c) { return shell_exec($c); }
function run_ls() { return `ls`; }
function send_mail(string $to) { return mail($to, 'Subject', 'Body'); }
function load_config(string $p) { return require $p; }
function query_param() { return filter_input(INPUT_GET, 'q'); }
function set_tz() { date_default_timezone_set('UTC'); }

// G6 catalogue gaps in existing strict categories
function now_obj() { return new \DateTimeImmutable(); }
function now_hr() { return hrtime(true); }
function now_gm() { return gmdate('Y-m-d'); }
function shuffled(array $xs) { shuffle($xs); return $xs; }
function pick(array $xs) { return array_rand($xs); }
function uid() { return uniqid(); }
function read_line($h) { return fgets($h); }
function read_lines(string $p) { return file($p); }
function write_line($h, string $s) { fputs($h, $s); }
function delete_file(string $p) { unlink($p); }
function to_stdout(string $s) { fprintf(STDOUT, '%s', $s); }

// G7 transitivity
function reads_global() { global $cart; return $cart; }
function calls_action() { return count(reads_global()); }

// G8 I/O through objects
function db_pdo(\PDO $pdo) { return $pdo->query('SELECT 1')->fetchAll(); }
function cache_get(string $k) { return \Cache::get($k); }

// G9 reading a mutable object argument
function first_name(\stdClass $user) { return $user->first_name; }

// G10 handing out a mutable reference
class Box { public array $items = []; public function &items(): array { return $this->items; } }
```

(The `G` numbers in the file comments are working labels, not the ranked gap numbers above. For example, `G2` is ranked gap 3 and `G7` is ranked gap 8.)

```
$ php bin/explicitness-checker /tmp/ec-scratch/gaps.php     # exit 2; --strict gives identical rows
| /tmp/ec-scratch/gaps.php | 4    | Scratch\control      | read from global variable $total |                  | Serious  |
| /tmp/ec-scratch/gaps.php | 9    | Scratch\sort_global  | read from global variable $list  |                  | Serious  |
| /tmp/ec-scratch/gaps.php | 48   | Scratch\reads_global | read from global variable $cart  |                  | Serious  |

$ php bin/explicitness-checker --props /tmp/ec-scratch/gaps.php   # exit 2; --strict --props gives identical rows
| /tmp/ec-scratch/gaps.php | 4    | Scratch\control      | read from global variable $total              |                                              | Serious  |
| /tmp/ec-scratch/gaps.php | 9    | Scratch\sort_global  | read from global variable $list               |                                              | Serious  |
| /tmp/ec-scratch/gaps.php | 19   | Scratch\bump         | read from static property Scratch\Counter::$n | wrote to static property Scratch\Counter::$n | Serious  |
| /tmp/ec-scratch/gaps.php | 48   | Scratch\reads_global | read from global variable $cart               |                                              | Serious  |
| /tmp/ec-scratch/gaps.php | 59   | Scratch\Box::items   | read from object property $this->items        |                                              | Serious  |
```

Every other function in `gaps.php` has no row in any mode.

`/tmp/ec-scratch/fp.php`:

```php
<?php
namespace Scratch;

enum Status: string { case A = 'a'; }
final class Money { public static function zero(): self { return new self(); } }
final class Point {
    public function __construct(public readonly int $x) {}
    public function getX(): int { return $this->x; }
}

function dump_to_string(array $x) { return print_r($x, true); }
function export_to_string(array $x) { return var_export($x, true); }
function format_ts(int $ts) { return date('Y-m-d', $ts); }
function zero() { return Money::zero(); }
function statuses() { return Status::cases(); }
```

```
$ php bin/explicitness-checker --strict --props /tmp/ec-scratch/fp.php   # exit 3
| /tmp/ec-scratch/fp.php | 8    | Scratch\Point::getX      | read from object property $this->x              |                                        | Serious  |
| /tmp/ec-scratch/fp.php | 11   | Scratch\dump_to_string   |                                                 | writes to standard output (print_r)    | Minor    |
| /tmp/ec-scratch/fp.php | 12   | Scratch\export_to_string |                                                 | writes to standard output (var_export) | Minor    |
| /tmp/ec-scratch/fp.php | 13   | Scratch\format_ts        | reads system time (date)                        |                                        | Critical |
| /tmp/ec-scratch/fp.php | 14   | Scratch\zero             | read from static method Scratch\Money::zero()   |                                        | Serious  |
| /tmp/ec-scratch/fp.php | 15   | Scratch\statuses         | read from static method Scratch\Status::cases() |                                        | Serious  |
```

The other modes show subsets of these rows:
- Default: rows 14 and 15 only (exit 2).
- `--strict`: rows 11–15 (exit 3).
- `--props`: rows 8, 14 and 15 (exit 2).

`/tmp/ec-scratch/flags.php`:

```php
<?php
namespace Scratch;

function log_total(int $t) { echo "Old total: $t"; return $t; }
function stamp() { return time(); }
function roll() { return random_int(1, 6); }
```

```
$ php bin/explicitness-checker /tmp/ec-scratch/flags.php          # exit 0
No implicit inputs or outputs found.

$ php bin/explicitness-checker --strict /tmp/ec-scratch/flags.php # exit 3
| /tmp/ec-scratch/flags.php | 4    | Scratch\log_total |                                                 | writes to standard output (echo) | Minor    |
| /tmp/ec-scratch/flags.php | 5    | Scratch\stamp     | reads system time (time)                        |                                  | Critical |
| /tmp/ec-scratch/flags.php | 6    | Scratch\roll      | reads from random number generator (random_int) |                                  | Critical |
```

`--props` gives the same result as default, and `--strict --props` the same as `--strict`.

By-reference reflection check:

```
$ php -r 'foreach(["sort","shuffle","preg_match","array_push","end"] as $f){echo $f," ",(new ReflectionFunction($f))->getParameters()[$f==="preg_match"?2:0]->isPassedByReference()?"byref":"val","\n";}'
sort byref
shuffle byref
preg_match byref
array_push byref
end byref
```

## Sources

- Eric Normand, *Grokking Simplicity* (Manning, 2021). Key passages, by line in a plain-text export:
  - Actions vs calculations: L1395–1461, L1610–1621, L2560–2580.
  - Actions spread, and the forms actions take: L4141–4291.
  - Inputs and outputs, explicit vs implicit: L4786–4852.
  - Extracting a calculation, with the mutating-argument example: L5225–5360 and L5455–5472.
  - Minimize implicit inputs and outputs: L6118–6160.
  - Copy-on-write, and reads vs writes: L7280–7380 and L8259–8293.
  - Defensive copying: L9077–9092 and L9282–9310.
  - Callbacks as explicit output: L25240–25340.
  - Functional architecture and databases: L31540–31546.
- `jonbaldie/explicitness-checker` at `origin/main` `963de64`:
  - `src/Detect/*.php`, `src/Detect/DetectorSet.php`, `src/Category.php` and `src/Cli/Severity.php`.
  - `src/Analyser.php`, `src/Walk/*.php`, `src/Scope/ScopeBoundary.php` and `src/PHPStan/ImplicitInputOutputRule.php`.
  - `README.md`.
- PHPStan function metadata: strings found by grepping `vendor/phpstan/phpstan/phpstan.phar`, in the version pinned by `origin/main:composer.lock`.
- GitHub issues for `jonbaldie/explicitness-checker`, via `gh issue list --state all`. None of them cover the gaps above.
