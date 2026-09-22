# Exploratory testing: Laravel installation (2026-09-22)

A pass over installing and running the checker in a Laravel app. It follows the draft
"Laravel" section of the README, which is uncommitted and not yet in a PR. It
drove the public surfaces a Laravel developer uses: `composer require` from
Packagist, `vendor/bin/explicitness-checker`, and `vendor/bin/phpstan analyse`
with Larastan.

## Environment

| | |
|---|---|
| Repo | `main` at `4eb62c2`, plus the uncommitted README Laravel section |
| Installed package | `jonbaldie/explicitness-checker` v1.0.0 (`d50afbb`) from Packagist |
| Laravel | 13.33.0 (`composer create-project laravel/laravel`) |
| PHP / Composer | 8.4.1 (Herd Lite, `memory_limit` 128M) / 2.8.3 |
| Larastan / PHPStan / php-parser | 3.12.2 / 2.2.14 / v5.9.0 |
| Scratch | `/tmp/ec-explore-laravel/`, one copy of a pristine `skeleton/` per journey |
| Evidence | `/tmp/ec-explore-laravel/evidence/` (local to the pass, not committed), cited as `NN` below |

## Journeys exercised

### 1. Install and gate CI with the CLI on `app`

Goal: follow the section's two commands and get a CI-usable exit code.

Ordinary path: `composer require jonbaldie/explicitness-checker --dev` resolved
v1.0.0 with no conflicts (`10`). On the skeleton, `./vendor/bin/explicitness-checker app`
printed `No implicit inputs or outputs found.` and exited 0 (`11`).

Next I added typical app code: a controller with constructor injection, a service,
a queued job, an Artisan command and a middleware. They mix facades and helpers
with raw PHP (`12-*`):

- **Default mode:** only `$_SERVER` was flagged (exit 2).
- **`--strict`:** added `getenv`, `date`, `random_int`, `file_put_contents` and `echo` (exit 3).
- **`--props`:** added `$this->orders` and `$this->ttl` (exit 2).
- **Never flagged, in any mode:** `DB::`, `Cache::`, `Storage::`, `Log::`,
  `now()`, `Str::uuid()`, `app()`, `storage_path()` and `$this->info()`.

This confirms the section's "Blind spot" bullet.

Variations (`13`):
- A composer script `explicitness-checker app` passes exit 2 through `composer explicitness`.
- `app/` with a trailing slash behaves the same as `app`.
- Running from inside `app/` behaves the same.
- `App` (case-insensitive filesystem) behaves the same.

### 2. PHPStan with Larastan

Goal: get `explicitness.*` errors next to Larastan's in one `phpstan analyse`.

- **Larastan's documented setup** (`includes: vendor/larastan/larastan/extension.neon`,
  no installer) plus `composer require --dev jonbaldie/explicitness-checker`:
  PHPStan runs, but only Larastan's rules load. The `$_SERVER` read is not reported
  (`23`). This matches the README, which only promises auto-loading with the installer.
- **Then the section's installer route** (`composer require --dev phpstan/extension-installer ...`):
  PHPStan aborts before analysing anything, with `This file is included multiple
  times: vendor/larastan/larastan/extension.neon` (`25`). Replayed from a clean
  copy with the same result (`26`). **Finding A.**
- **Fix (i):** keep the installer and drop the manual Larastan include. Both load:
  `argument.type` and `explicitness.superglobal` (`27`).
- **Fix (ii):** skip the installer, and include both neon files by hand with
  `explicitness.strict: true`. Both load, and the six strict findings match the CLI's
  `--strict` rows one for one (`28`).

### 3. Follow the README against what Packagist installs

Goal: every behaviour the README documents works in the version Composer installs.

Since v1.0.0, the README has gained three promises: unknown options fail, parse
errors exit ≥2, and `--min-explicitness`. The Packagist page shows that README
(`31`). On v1.0.0 (`30`, replayed in `31`):
- `--stict app` exits 0, because unknown options are silently ignored.
- An unparseable file under `app/` prints `Parse error in …` and exits 0.
- `--min-explicitness=80` is silently ignored, not rejected.

On `main`, all three exit 2 or behave as documented. **Finding B**, filed as #68.

## Confirmed findings

| | Where | Impact | Status |
|---|---|---|---|
| A | Draft README Laravel section | If you already use Larastan the way its docs say, following our installer advice makes PHPStan abort with "included multiple times", and it analyses nothing Fixed in the README before merge: the section now gives both working setups; not filed |
| B | Release v1.0.0 vs README | CI built from the README passes on typo'd flags and on unparseable files; `--min-explicitness` silently does nothing | [#68](https://github.com/jonbaldie/explicitness-checker/issues/68) |

**A, replay:**
1. Start from a fresh Laravel app.
2. `composer require --dev larastan/larastan jonbaldie/explicitness-checker`.
3. Write Larastan's documented `phpstan.neon`, which includes `vendor/larastan/larastan/extension.neon`.
4. Run `composer require --dev phpstan/extension-installer`.
5. Run `vendor/bin/phpstan analyse`.

Expected: Larastan and `explicitness.*` errors. Actual: exit 1 with the
duplicate-include message and no analysis. Seen twice (`25`, `26`).

## Rejected candidates

- **PHPStan crashes with "reached configured PHP memory limit: 128M"** (`21`).
  It happened on the first Larastan run, before our extension was loaded at all.
  That makes it Larastan and PHPStan under this machine's 128M `memory_limit`
  (`22`). Every later PHPStan run used `--memory-limit=1G`; that is an intervention.

## Unresolved

None.

## Usability observations

- **`--props` flags constructor injection.** `$this->orders` in a controller is
  reported Serious, but it's Laravel's idiomatic way to be explicit about
  dependencies. This is documented behaviour. It means `--props` will be noisy on
  almost any Laravel app. *Suggestion:* the Laravel section could say so.
- **Facade and helper blind spot.** A clean default or `--strict` run on typical
  Laravel code says little about its I/O, which mostly goes through facades and
  helpers. The section now states this. *Suggestion:* consider recognising facades
  and helpers as a feature.
- **Seen before this pass, not re-exercised:** only the first path argument is
  used, and later ones are silently ignored (`src/Cli/ArgumentParser.php:18`).

## Limitations

- **Memory limit:** PHPStan ran with `--memory-limit=1G`, as described above.
- **Not exercised:**
  - Laravel versions other than 13.33, and Sail/Docker setups.
  - Packages (as opposed to apps).
  - `routes/` closures under the `app`-only command.
  - Octane and Livewire code.
  - A real-world Laravel codebase: all app code here was hand-written to be typical.
