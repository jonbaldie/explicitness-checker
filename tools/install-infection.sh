#!/bin/sh
# Downloads the pinned Infection PHAR to tools/infection.phar and checks its hash.
#
# Infection is a PHAR, not a Composer dev dependency: every Infection release
# that runs on PHP 8.0 requires nikic/php-parser ^4, and this package requires
# ^5. The PHAR bundles its own prefixed copy, so there is no conflict.
set -eu

version="0.26.19"
sha256="6247d135ccdaa260f21a82bb4f443c2d65de887187d30f88b0284fec593d16e4"
target="$(dirname "$0")/infection.phar"

if [ -f "$target" ] && php -r 'exit(hash_file("sha256", $argv[1]) === $argv[2] ? 0 : 1);' "$target" "$sha256"; then
    exit 0
fi

curl -sSfL -o "$target.tmp" "https://github.com/infection/infection/releases/download/${version}/infection.phar"

if ! php -r 'exit(hash_file("sha256", $argv[1]) === $argv[2] ? 0 : 1);' "$target.tmp" "$sha256"; then
    rm -f "$target.tmp"
    echo "infection.phar checksum mismatch" >&2
    exit 1
fi

mv "$target.tmp" "$target"
chmod +x "$target"
