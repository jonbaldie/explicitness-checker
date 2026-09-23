# Exploratory testing: argument mutation and the strict catalogue (2026-09-23)

A pass over the #72 branch (`feat/72-normand-implicit-io`) against `origin/main`. It covered:
- writes through arguments;
- `static` variables and by-reference captures;
- static properties in default mode;
- by-reference built-ins;
- the wider `--strict` catalogue.

It drove the CLI (`bin/explicitness-checker`) and the PHPStan extension (`extension.neon`) on hand-written domain and infrastructure code. Confirmed bugs in the diff were taken through `/diagnosing-bugs` and fixed on the branch.

## Environment

| | |
|---|---|
| Repo | `feat/72-normand-implicit-io` at `63d4219`, compared with `origin/main` at `963de64` |
| Differential | A `git worktree` of `origin/main` with its own `vendor`. A symlinked `vendor` autoloads the branch's `src` through the real path, so it gives wrong results |
| PHP / PHPStan / php-parser | 8.4.1 / 2.2.14 / v5.9.0 |
| Scratch | `/tmp/ec-explore-72/` (`j1/`, `j2/`, `j3/`, `replay/`) |
| Evidence | `/tmp/ec-explore-72/evidence/` (local to the pass, not committed), cited as `NN` below |

## Journeys exercised

### 1. Default mode on domain code

Goal: a function that changes its caller's data or keeps hidden state is reported, and a pure one isn't.

Ordinary path (`10`), all reported correctly:
- `$cart->items[] = $item` on an object argument;
- `$prices[$k] = ...` on `array &$prices`;
- `static $cache`;
- `use (&$sum)`;
- `self::$calls++`.

More shapes (`13`), also reported correctly:
- nested property writes, `unset`, and increments through arguments;
- `list()` into a by-reference parameter;
- by-reference closure and variadic parameters.

Local mutation and reassigning a by-value parameter stayed silent.

Variations:
- `--props` added only `$this->seen` (`14`).
- Dynamic static names (`15`).
- A probe with foreach by reference (`11`), with the same probe on `main` (`12`). This led to **Finding A**.

### 2. `--strict` on infrastructure code

Goal: every new catalogue entry is reported under its category, and calls given explicit values are not.

Ordinary path (`20`): 22 functions, each reported correctly. They cover clock, random, output, include, process, network, database, mail, runtime configuration, file system and `filter_input`.

These stayed silent, correctly:
- explicit timestamps and dates;
- a seeded `Randomizer`;
- `print_r`/`var_export` with `return: true`;
- a full `mktime`;
- PDO and `strtotime`, which are out of scope.

Variation, argument shapes (`21`): named arguments, `null`, unpacking and first-class callables. Checked against PHP (`22`), this gave **Finding C**.

### 3. PHPStan extension

Goal: the new identifiers reach PHPStan, and can be filtered and baselined.

- **`explicitness.strict: true`** (`30`, `31`): the same 27 functions as the CLI on the same source (`32`). The new identifiers all appeared: `argumentMutation`, `staticVariable`, `capturedReference`, `network`, `database`, `process`, `mail`, `include` and `runtimeConfig`.
  - PHPStan reports each access on its own line, where the CLI reports the function's line. This is existing behaviour.
- **`ignoreErrors` by identifier** for `runtimeConfig` and `argumentMutation` removed exactly those rows (`33`).
- **Default config** reported `self::$total` but not `$this->n`. **`props: true`** added `$this->n` (`34`). This matches the `extension.neon` note.
- **Baseline** (`34`):
  - `--generate-baseline` kept `identifier: explicitness.*` on every entry.
  - Rerunning with the baseline gave `[OK] No errors`, exit 0.

## Confirmed findings

| | Where | Impact | Status |
|---|---|---|---|
| A | #72 argument mutation (and existing global/static/superglobal writes) | A write through a reference taken by `foreach (... as &$v)`, `$r = &$x` or `[&$x] = ...` wasn't reported, so the new check could be dodged. Affected by-reference parameters, object arguments, globals, `static` variables and superglobals | Fixed on the branch |
| C | #72 strict catalogue | `new DateTime(null)`, `date_create(null)` and `new Random\Randomizer(null)` read the clock or the default engine, but weren't reported. `date('Y', null)` already was | Fixed on the branch |

**A, replay:**
1. `printf '<?php\nfunction f(array &$a) { foreach ($a as &$v) {} }\n' > a.php`
2. `bin/explicitness-checker a.php`

Expected: `f` has the output `wrote to argument $a`, exit 2, as with `sort($a)`. The README promised this: "Writes through arguments (`$cart[] = $item` or `sort($cart)` with `array &$cart` ...)". Actual: `No implicit inputs or outputs found.`, exit 0.

Seen twice (`40`). Minimised (`41`):
- The loop body isn't load-bearing.
- The `&` is: by-value iteration is correctly silent.
- By-value parameters are correctly silent.

Same gap:
- the object-argument property `$cart->items`;
- the write half for `global`, `static` and `$_SESSION` iteration (the read was reported);
- `$r = &$a;`, and `[&$x] = $a` (`44`).

`main` has the same foreach gap for globals (`12`).

**A, root cause:**
- Tagged instrumentation (`42`) showed `Foreach_::byRef` was `true`, but the iterated `$a` was only ever walked with `isWrite=false`. `ForeachRule` walked the iterated expression as a read and ignored `byRef`.
- `AssignRef` had no write rule except the `$GLOBALS` alias case.
- For destructuring, php-parser puts `&` on the list item, not on the `Foreach_` or on an `AssignRef` (`45`).

Rejected hypotheses:
- The detector dropped a write event: no write event arrived.
- The parser lost the `&`: `byRef=true`.

Fix: all three forms now walk the referenced expression as read and then written, as `ByReferenceCallRule` does for `sort($a)` (`43`).

One side effect: `$alias = &$_SESSION['key']; $alias = 1;` now reports `wrote to superglobal $_SESSION`. #45's fix had pinned it as read-only, but the function does write the session, so the pins in `ReadWriteContextTest` and `ImplicitInputOutputRuleTest` were updated.

**C, replay:**
1. `printf '<?php\nfunction r() { return new \\Random\\Randomizer(null); }\nfunction d() { return new \\DateTime(null); }\n' > c.php`
2. `bin/explicitness-checker --strict c.php`

Expected: `r` reads randomness and `d` reads the clock. PHP 8.4 builds a `Random\Engine\Secure` Randomizer and the current time for these calls, with a deprecation notice for `DateTime(null)` (`22`). Actual: `No implicit inputs or outputs found.`

Seen twice (`40`). Minimised (`41`): positional and named `null`, in `new` and in `date_create*`.

**C, root cause:**
- `CallArguments::readsClock()` treated only an absent argument as "now".
- `NewExpressionDetector` used `isEmpty()` for the Randomizer.
- A one-variable probe of each turned the loop green. A name-resolution problem was already ruled out, because `date('Y', null)` is reported through the same `null` check.

Fix: `readsClock()` also accepts `null`, and the Randomizer check uses `omits(0, 'engine')`.

Loop for both findings: `/tmp/ec-explore-72/replay/loop.sh`, red before the fix and green after. The regression tests are in `SourceCheckerTest::testReportsByReferenceForeachAndReferenceAssignmentAsWrites` and in the `StrictCatalogueTest` clock and randomness cases.

### Pre-existing, not in the diff

| | Where | Impact | Status |
|---|---|---|---|
| B | Static properties, on `main` too | `Svc::${$n} = 1` and `return Svc::${$n};` aren't reported in any mode, while `Svc::$a = 1` and `$c::$a = 1` are (`15`) | Filed as [#73](https://github.com/jonbaldie/explicitness-checker/issues/73) |

**B, replay:**
1. `printf '<?php\nclass Svc { public static $a;\n  public function w(string $n): void { Svc::${$n} = 1; } }\n' > b.php`
2. `bin/explicitness-checker --props b.php`

Expected: `wrote to static property Svc::...`. Actual: no row. The same happens on `origin/main`.

## Rejected candidates

- **`zeroPrices`: `foreach ($items as $item) { $item->price = 0; }`** isn't reported. The spec requires the fetch chain to be rooted at a parameter, and `$item` is a local.
- **`$o->customer()->name = 'x'`** isn't reported. A method call breaks the chain, and I/O through objects is out of scope.
- **Backticks are described as `(shell_exec)`.** The spec says they are.
- **`ob_get_clean` is reported as standard output.** Spec story 51.
- **`new DateTime(' now ')` and concatenated date strings** aren't reported. Only the literal `'now'` and `''` are in scope.
- **`class.notFound` for `Random\Randomizer` in journey 3** (`31`). The scratch project had no `composer.json`, so PHPStan used a default `phpVersion`. It's gone with `phpVersion: 80400`.

## Unresolved

None.

## Usability observations

- **Aliased class names are reported under the resolved name.** `use DateTimeImmutable as Clock; new Clock()` is described as `(DateTimeImmutable)`. This is correct, but the description doesn't match the source text.
- **First-class callables count as calls.** `time(...)` and `date(...)` are reported as reading time. This is conservative, and a callable that's never called is rare.
- **`error_reporting(...)` is labelled a write.** The `...` there is a first-class-callable placeholder, not an argument. This is minor.
- **Two line conventions.** PHPStan reports the access line and the CLI reports the function line. It's worth knowing when moving between the two.

## Limitations

- **Hand-written code:** every probe was written for the pass, not taken from a real codebase.
- **Memory limit:** the first PHPStan run used `--memory-limit=1G`.
- **Extensions:** by-reference positions come from the PHP running the checker, so they depend on the extensions loaded. Only this machine's set was used.
- **Not exercised:**
  - reference cases beyond foreach, `=&` and destructuring, such as `global` rebinding and returning by reference;
  - Laravel and Symfony code;
  - PHP versions other than 8.4.
