#!/bin/sh
set -u
root=$(git rev-parse --show-toplevel)
cd "$root"
work=.run/journeys
rm -rf "$work"
mkdir -p "$work/project/core" "$work/project/vendor" "$work/project/generated"
trap ':' EXIT HUP INT TERM
cat > "$work/project/core/clock.php" <<'PHP'
<?php
function fixed_date() { return new DateTimeImmutable('2024-01-01'); }
function relative_date() { return new DateTimeImmutable('tomorrow'); }
function fixed_format($date) { return DateTimeImmutable::createFromFormat('!Y-m-d', $date); }
function partial_format($date) { return DateTimeImmutable::createFromFormat('Y-m-d', $date); }
PHP
cat > "$work/project/core/cart.php" <<'PHP'
<?php
function copied_array($cart) { $copy = $cart; $copy[] = 1; }
function shared_object($cart) { $copy = $cart; $copy[0]->price = 1; }
function reference_sort(&$cart) { sort($cart); }
function shallow_clone($cart) { $copy = clone $cart; $copy->price = 1; }
PHP
cat > "$work/project/vendor/vendor.php" <<'PHP'
<?php
function vendor_input() { return $_GET['v']; }
PHP
cat > "$work/project/generated/generated.php" <<'PHP'
<?php
function generated_input() { return $_POST['v']; }
PHP
printf 'Fixture hashes before analysis:\n'
sha256sum "$work"/project/core/*.php "$work"/project/vendor/*.php "$work"/project/generated/*.php
run() {
  printf '\n$ bin/explicitness-checker %s\n' "$*"
  php bin/explicitness-checker "$@"
  result=$?
  printf 'exit=%s\n' "$result"
}
run --strict "$work/project/core/clock.php"
run "$work/project/core/cart.php"
run "$work/project"
run --exclude=generated "$work/project"
run --include-pattern='clock\.php$' --include-pattern='cart\.php$' --exclude-pattern='cart\.php$' "$work/project"
run --strict --include-pattern='clock\.php$' --include-pattern='cart\.php$' --exclude-pattern='cart\.php$' "$work/project"
run --include-pattern='(' "$work/project"
printf '\nFixture hashes after analysis:\n'
sha256sum "$work"/project/core/*.php "$work"/project/vendor/*.php "$work"/project/generated/*.php
