#!/usr/bin/env bash
# Snapshot-compare the console output, the JUnit XML output and the code
# coverage of `doctest examples/`. Regenerate the snapshots with --update
# after intentional output changes.
set -euo pipefail
cd "$(dirname "$0")/.."

snapshot=tests/data/expected/examples-output.txt
junit_snapshot=tests/data/expected/examples-junit.xml
coverage_snapshot=tests/data/expected/examples-coverage.xml
update=false

if [ "${1:-}" = '--update' ]; then
    update=true
fi

# coverage snapshot: only meaningful when pcov is available & its directory
# covers the analyzed paths (both are PHP_INI_SYSTEM settings)
pcov_ok() {
    php -d pcov.enabled=1 -d pcov.directory="$PWD" -r 'exit(function_exists("pcov\\start") ? 0 : 1);' 2>/dev/null
}

# one run produces all three artifacts: console, junit & clover (the `-d`
# ini overrides must precede the script, while `--coverage-clover` belongs to
# doctest and must follow it — otherwise php-cli claims the flag as its own)
doctest_ini=()
doctest_cli=()

if pcov_ok; then
    doctest_ini=(-d pcov.enabled=1 -d pcov.directory="$PWD")
    doctest_cli=(--coverage-clover /tmp/doctest-coverage.xml)
else
    unset coverage_snapshot
fi

set +e
php "${doctest_ini[@]}" bin/doctest --no-ansi --junit /tmp/doctest-junit.xml "${doctest_cli[@]}" examples > /tmp/doctest-output.txt 2>&1
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

# stack traces embed the project root path and machine-dependent
# frame arguments/line numbers, drop them
normalize_junit() {
    sed -E -e 's/ time="[0-9]+(\.[0-9]+)?"/ time=""/g' -e "s|$PWD/||g" \
        -e 's/^([[:space:]]*)#[0-9]+ \{main\}<\/failure>$/\1<\/failure>/' \
        | sed -E -e '/^Stack trace:$/d' -e '/^#[0-9]+ /d'
}
normalize_junit < /tmp/doctest-junit.xml > /tmp/doctest-junit-normalized.xml

if $update; then
    cp /tmp/doctest-normalized.txt "$snapshot"
    echo "updated $snapshot"
    cp /tmp/doctest-junit-normalized.xml "$junit_snapshot"
    echo "updated $junit_snapshot"
else
    diff -u "$snapshot" /tmp/doctest-normalized.txt
    diff -u "$junit_snapshot" /tmp/doctest-junit-normalized.xml
fi

if [ -n "${coverage_snapshot:-}" ]; then
    # blank generated/timestamp (they vary per run), make paths relative,
    # drop crap (algorithm can differ across php-code-coverage majors)
    normalize_coverage() {
        sed -E -e 's/generated="[0-9]+"/generated=""/g' -e 's/timestamp="[0-9]+"/timestamp=""/g' \
            -e "s|$PWD/||g" -e 's/ crap="[0-9]+(\.[0-9]+)?"/ crap=""/g'
    }
    normalize_coverage < /tmp/doctest-coverage.xml > /tmp/doctest-coverage-normalized.xml

    if $update; then
        cp /tmp/doctest-coverage-normalized.xml "$coverage_snapshot"
        echo "updated $coverage_snapshot"
    else
        diff -u "$coverage_snapshot" /tmp/doctest-coverage-normalized.xml
    fi
else
    if $update; then
        echo "::warning::skipping coverage snapshot: pcov unavailable or misconfigured"
    else
        echo "skipping coverage snapshot: pcov unavailable or misconfigured"
    fi
fi
