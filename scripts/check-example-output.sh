#!/usr/bin/env bash
# Snapshot-compare the output of `doctest examples/`.
# Regenerate the snapshot with --update after intentional output changes.
set -euo pipefail
cd "$(dirname "$0")/.."

snapshot=tests/data/expected/examples-output.txt

set +e
php bin/doctest --no-ansi examples > /tmp/doctest-output.txt 2>&1
status=$?
set -e

# factorial.php documents 3 deliberate failures, so the run is expected to exit 1
if [ "$status" -ne 1 ]; then
    echo "::error::doctest exited with $status, expected 1"
    cat /tmp/doctest-output.txt
    exit 1
fi

normalize() {
    sed -e 's/\x1b\[[0-9;]*m//g' -e 's/\r/\n/g' \
        | sed -E -e '/^[[:space:]]*[0-9]+([\/][0-9]+)?[[:space:]]*\[/d' \
        | sed -e 's/[[:space:]]*$//' \
        | cat -s
}
normalize < /tmp/doctest-output.txt > /tmp/doctest-normalized.txt

if [ "${1:-}" = '--update' ]; then
    cp /tmp/doctest-normalized.txt "$snapshot"
    echo "updated $snapshot"
    exit 0
fi

diff -u "$snapshot" /tmp/doctest-normalized.txt
