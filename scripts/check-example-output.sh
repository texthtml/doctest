#!/usr/bin/env bash
# Snapshot-compare the console output and the JUnit XML output of
# `doctest examples/` for both backends, plus the code coverage of the
# reflection one. Regenerate the snapshots with --update after intentional
# output changes.
#
# the static backend is expected to be indistinguishable from the reflection
# one on this corpus: both legs diff against the same snapshots, so any drift
# between backends fails here too.
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

# the reflection leg's run additionally produces a clover artifact: console,
# junit & clover (the `-d` ini overrides must precede the script, while
# `--coverage-clover` belongs to doctest and must follow it — otherwise
# php-cli claims the flag as its own)
doctest_ini=()
coverage_cli=()

if pcov_ok; then
    doctest_ini=(-d pcov.enabled=1 -d pcov.directory="$PWD")
    coverage_cli=(--coverage-clover /tmp/doctest-coverage.xml)
else
    unset coverage_snapshot
fi

# factorial.php documents 3 deliberate failures, so any run is expected to exit 1
check_exit_status() {
    local status=$1 output=$2
    if [ "$status" -ne 1 ]; then
        echo "::error::doctest exited with $status, expected 1"
        cat "$output"
        exit 1
    fi
}

normalize() {
    sed -e 's/\x1b\[[0-9;]*m//g' -e 's/\r/\n/g' \
        | sed -E -e '/^[[:space:]]*[0-9]+([\/][0-9]+)?[[:space:]]*\[/d' \
        | sed -e 's/[[:space:]]*$//' \
        | cat -s
}

# stack traces embed the project root path and machine-dependent
# frame arguments/line numbers, drop them
normalize_junit() {
    sed -E -e 's/ time="[0-9]+(\.[0-9]+)?"/ time=""/g' -e "s|$PWD/||g" \
        -e 's/^([[:space:]]*)#[0-9]+ \{main\}<\/failure>$/\1<\/failure>/' \
        | sed -E -e '/^Stack trace:$/d' -e '/^#[0-9]+ /d'
}

# runs one backend and leaves its normalized artifacts at the given prefix
run_leg() {
    local prefix=$1
    shift

    set +e
    php "${doctest_ini[@]}" bin/doctest --no-ansi --junit "/tmp/${prefix}-junit.xml" "$@" "${coverage_cli[@]}" examples > "/tmp/${prefix}-output.txt" 2>&1
    local status=$?
    set -e

    check_exit_status "$status" "/tmp/${prefix}-output.txt"

    normalize < "/tmp/${prefix}-output.txt" > "/tmp/${prefix}-normalized.txt"
    normalize_junit < "/tmp/${prefix}-junit.xml" > "/tmp/${prefix}-junit-normalized.xml"
}

run_leg doctest
run_leg doctest-static --static-scan

if $update; then
    # both legs must stay identical anyway, so either artifact can seed the snapshot
    cp /tmp/doctest-normalized.txt "$snapshot"
    echo "updated $snapshot"
    cp /tmp/doctest-junit-normalized.xml "$junit_snapshot"
    echo "updated $junit_snapshot"
else
    for prefix in doctest doctest-static; do
        diff -u "$snapshot" "/tmp/${prefix}-normalized.txt"
        diff -u "$junit_snapshot" "/tmp/${prefix}-junit-normalized.xml"
    done
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
