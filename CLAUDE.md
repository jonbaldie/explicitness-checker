# Explicitness Checker

A PHP command-line tool (`bin/explicitness-checker`) that uses `nikic/php-parser` to report implicit inputs and outputs in PHP functions and methods. See `README.md` for flags and behaviour.

## Verification

CI in `.github/workflows/` defines the required checks: PHPStan on `bin/explicitness-checker` and fixture runs against `test-fixtures/`. Run the same commands locally before opening a PR.

## Agent skills

### Issue tracker

Issues live in GitHub Issues for `jonbaldie/explicitness-checker`, via the `gh` CLI. See `docs/agents/issue-tracker.md`.

### Triage labels

The five default labels (`needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`). See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: `CONTEXT.md` plus `docs/adr/` at the repo root. See `docs/agents/domain.md`.
