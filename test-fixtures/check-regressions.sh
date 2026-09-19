#!/usr/bin/env bash
# Regression checks for #4 (pattern delimiters) and #7 ($_ENV severity).
# Runs the real CLI on test-fixtures/env-access.php. Exits non-zero on any failure.
# Run from the repo root: bash test-fixtures/check-regressions.sh

cd "$(dirname "$0")/.." || exit 1

fixture="test-fixtures/env-access.php"
failures=0

# check <name> <expected exit> <expected stdout substring> -- <cli args...>
check() {
    local name=$1 expected_exit=$2 expected_text=$3
    shift 4
    local out err code
    err=$(mktemp)
    out=$(php bin/explicitness-checker "$@" 2>"$err")
    code=$?
    if [ "$code" -ne "$expected_exit" ]; then
        echo "FAIL $name: exit $code, expected $expected_exit"
        failures=$((failures + 1))
    elif ! grep -qF -- "$expected_text" <<<"$out"; then
        echo "FAIL $name: output missing '$expected_text'"
        failures=$((failures + 1))
    elif grep -qi "warning" "$err" || grep -qi "warning" <<<"$out"; then
        echo "FAIL $name: PHP warning emitted"
        failures=$((failures + 1))
    else
        echo "ok   $name"
    fi
    rm -f "$err"
}

# #7: $_ENV read and write are Critical.
check "env read/write is Critical" 3 "Critical violations: 2" -- "$fixture"

# #4: patterns containing "/" work without warnings.
check "include-pattern with / matches" 3 "Critical violations: 2" -- --include-pattern="test-fixtures/" "$fixture"
check "include-pattern (README example) matches" 3 "Critical violations: 2" -- --include-pattern="test-fixtures/.*\.php$" "$fixture"
check "include-pattern with pre-escaped \\/ matches" 3 "Critical violations: 2" -- --include-pattern="test-fixtures\/env" "$fixture"
check "include-pattern with / not matching excludes" 0 "No PHP files found" -- --include-pattern="src/" "$fixture"
check "exclude-pattern with / excludes" 0 "No PHP files found" -- --exclude-pattern="test-fixtures/" "$fixture"
check "exclude-pattern with / not matching keeps file" 3 "Critical violations: 2" -- --exclude-pattern="src/" "$fixture"
check "include-pattern with / on a directory" 3 "Critical violations: 2" -- --include-pattern="/env-access\.php$" test-fixtures

if [ "$failures" -ne 0 ]; then
    echo "$failures check(s) failed"
    exit 1
fi
echo "all checks passed"
