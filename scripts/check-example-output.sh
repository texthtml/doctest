#!/usr/bin/env bash
# Snapshot-compare the console output and the JUnit XML output of
# `doctest examples/`. Regenerate both snapshots with --update after
# intentional output changes.
set -euo pipefail
cd "$(dirname "$0")/.."

snapshot=tests/data/expected/examples-output.txt
junit_snapshot=tests/data/expected/examples-junit.xml

set +e
php bin/doctest --no-ansi --junit /tmp/doctest-junit.xml examples > /tmp/doctest-output.txt 2>&1
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
else
    diff -u "$snapshot" /tmp/doctest-normalized.txt
fi

# JUnit snapshot: time attributes vary run to run, blank them out;
# stack traces embed the project root path and machine-dependent
# frame arguments/line numbers, drop them
normalize_junit() {
    sed -E -e 's/ time="[0-9]+(\.[0-9]+)?"/ time=""/g' -e "s|$PWD/||g" \
        -e 's/^([[:space:]]*)#[0-9]+ \{main\}<\/failure>$/\1<\/failure>/' \
        | sed -E -e '/^Stack trace:$/d' -e '/^#[0-9]+ /d'
}
normalize_junit < /tmp/doctest-junit.xml > /tmp/doctest-junit-normalized.xml

if [ "${1:-}" = '--update' ]; then
    cp /tmp/doctest-junit-normalized.xml "$junit_snapshot"
    echo "updated $junit_snapshot"
else
    diff -u "$junit_snapshot" /tmp/doctest-junit-normalized.xml
fi
