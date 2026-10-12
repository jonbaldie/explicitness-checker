# Exploratory testing: copies, aliases, clock strings and process registers (2026-10-12)

A pass over `main` at `daea69a` (1.3.2) that measured the checker against Eric Normand's *Grokking Simplicity*. It stayed off the surfaces covered on 2026-10-10 (pipes, reference `unset`, catalogue direction). Five confirmed findings were filed as [#159](https://github.com/jonbaldie/explicitness-checker/issues/159)–[#163](https://github.com/jonbaldie/explicitness-checker/issues/163).

## Status when this report was saved

At `2c9ea0e`, the same day:

- **#159 is fixed by #166.** The `a.php` reproducer now reports nothing.
- **#160 is fixed by #172.** All six `b.php` functions are now reported as `wrote to argument`.
- **#161, #162 and #163 are open.** Their reproducers give the same output as on `daea69a`.

## Environment

- Checker: `jonbaldie/explicitness-checker` at `daea69a` (1.3.2).
- Runtime: PHP 8.4.1 CLI and PHPStan 2.2.14, with the checker's `extension.neon` at level 0. The configs are `default.neon` and `strict.neon`, which sets `explicitness.strict: true`.
- Interfaces exercised: the CLI (`bin/explicitness-checker [--strict] <file>`) and the PHPStan rule. Every fixture gave the same findings through both.
- Oracle: Eric Normand, *Grokking Simplicity*. Line numbers refer to the plain-text edition.
- Fixtures: scratch files, not committed. Each issue body carries its full reproducer, the runtime output, and the CLI and PHPStan output from two clean runs.

## Journeys

1. **Copies and argument mutation.** Normand's remedy for implicit outputs is copy-on-write: make a copy, modify the copy, return the copy. The goal was to check two things: that it passes clean, and that real mutation of caller data is caught.
2. **Time and randomness.** The goal was to confirm that every call whose result depends on when it runs is reported in `--strict`, and that deterministic variants are not.
3. **Process-global state.** The goal was to confirm that reads and writes of state shared by the whole process are reported in `--strict`, alongside the already-catalogued `setlocale`/`ini_get`.

For each journey I tried the ordinary path, then variations (rebinding, aliasing, foreach, destructuring, shallow clone; literal, relative, whitespace and format variants; seeded and unseeded engines; read and write forms of setters). I ran each fixture at runtime to establish ground truth before comparing the checker's verdict.

## Confirmed

Each finding was replayed twice from clean files, with identical results and CLI/PHPStan parity.

| # | Type | Finding | User impact |
|---|---|---|---|
| [#159](https://github.com/jonbaldie/explicitness-checker/issues/159) | bug | `$cart = clone $cart; $cart->items[] = $x; return $cart;` is reported as `wrote to argument $cart`. The caller is untouched (`original: 0, updated: 1`). | Penalises Normand's prescribed remedy. The verdict depends on the variable's name, because a separate `$copy` passes. |
| [#160](https://github.com/jonbaldie/explicitness-checker/issues/160) | bug | Writes to an argument's object through a local are missed: plain assignment, foreach value, destructuring, alias, and a nested write after a shallow clone. All five empty the caller's cart at runtime. Only the syntactically rooted `$carts[0]->items = []` is reported. | False negatives on the commonest accidental implicit output ("loop over line items and update each"). |
| [#161](https://github.com/jonbaldie/explicitness-checker/issues/161) | enhancement | `--strict` misses clock reads: relative literals (`'tomorrow'`, `'+1 week'`), `' now '`, `strtotime` (absent from the catalogue), and `createFromFormat` without `!`/`|`. | The README says the date family is "reported only when they read the clock", which isn't true for these. #72 deferred relative strings, and the issue proposes a decidable rule: `strtotime($s, 0) !== strtotime($s, 1e6)`. |
| [#162](https://github.com/jonbaldie/explicitness-checker/issues/162) | bug | `new Randomizer(new Secure())` and unseeded `Mt19937()`, `Xoshiro256StarStar()`, `PcgOneseq128XslRr64()` and `Mt19937(null)` are not reported. Their rolls vary between calls; only seeded engines repeat. | Contradicts #72's stated intent: "so that seeded, deterministic randomness stays explicit". Adding `new Secure()` silences the finding without changing behaviour. |
| [#163](https://github.com/jonbaldie/explicitness-checker/issues/163) | enhancement | `--strict` misses direct accessors of process state: the JSON, PCRE, last-error and libxml error registers, `libxml_use_internal_errors`, `mb_internal_encoding`, `chdir`/`getcwd`, and `umask`. | `last_json_error()` returned 4, then 0, with the same (empty) arguments. The libxml flag and cwd persist after the call. These are the same kind of state as the catalogued `setlocale`/`error_reporting`. The checker uses `error_get_last()` itself (`src/Cli/FileFilter.php:182`). |

## Rejected (checked; the checker is right)

- **Possible false positives on by-value array parameters.** None are reported, which is correct because arrays are copied on write. Tested forms:
  - `foreach ($items as &$i)`
  - `unset($items['k'])`
  - `$s[0] = 'x'`
  - `settype($items, …)`
  - `$r = &$items['a']`
  - `array_push($items, 1)`
  - `sort($items)`
- **`unset($obj->x)` on an object parameter.** This is reported, which is correct because it changes the caller's object.
- **Copy-on-write into a separate local** (`$copy = clone $cart; $copy->items[] = $x`). This is not reported, which is correct.
- **Controls that stayed quiet, correctly:**
  - an absolute date literal
  - `createFromFormat('!H:i', …)`
  - `strtotime('+1 day', $now)`
  - `Mt19937(42)`
  - `Xoshiro256StarStar(42)`
- **Controls that were reported, correctly:**
  - `'NOW'` (case-insensitive)
  - `setlocale`

## Considered and not filed

- **Enum `cases()` and `Money::zero()`-style static factories reported as static calls.** #72 explicitly lists these as a non-goal.
- **`sleep`/`usleep`.** #72 deferred these, and whether they are an implicit output is arguable.
- **`memory_get_usage`, `getmypid`, `gethostname`, `debug_backtrace`.** Mentioned in #163 only as possible extensions.
- **Mutating method calls** (`DateTime::modify`, `ArrayObject::append`). #72 lists these as out of scope, and they need type information.

## Not exercised, and limitations

- **Closures.** By-value closure and arrow-function captures of a parameter followed by a property write were not tested. #160 lists them as out of scope.
- **Frontends.** Only the CLI table output and the PHPStan raw output were exercised. The JSON output and the `--props` flag were not.
- **PHP versions.** Everything ran on PHP 8.4.1 only. The PHPStan config targets `phpVersion: 80500`. By-reference detection depends on the loaded extensions (README), but none of the findings involve by-reference built-ins.
- **Sources of the claims.** The real-world libxml count in #163 comes from one private Laravel 12 app, anonymised. The book line numbers refer to one plain-text edition.
