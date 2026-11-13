#!/usr/bin/env bash
# Compare doctest output for examples/ against committed snapshots in
# tests/data/expected/, or regenerate them with --update.
#
# usage:
#   scripts/check-example-output.sh            # compare
#   scripts/check-example-output.sh --update   # regenerate snapshots
set -euo pipefail
cd "$(dirname "$0")/.."

declare -A expected_exit=(
    [examples/Ratio.php]=0
    [examples/factorial.php]=1 # documents 3 deliberate failures, see its docblocks
)

# lowercase the filename for the snapshot path (ratio-output.txt)
declare -A snapshot_for=(
    [examples/Ratio.php]=ratio-output.txt
    [examples/factorial.php]=factorial-output.txt
)

update=false
if [ "${1:-}" = '--update' ]; then
    update=true
fi

normalize() {
    sed -e 's/\x1b\[[0-9;]*m//g' -e 's/\r/\n/g' \
        | sed -E -e '/^[[:space:]]*[0-9]+([\/][0-9]+)?[[:space:]]*\[/d' \
        | sed -e 's/[[:space:]]*$//' \
        | cat -s
}

status=0
for example in "${!expected_exit[@]}"; do
    snapshot="tests/data/expected/${snapshot_for[$example]}"
    set +e
    php bin/doctest --no-ansi "$example" > /tmp/doctest-output.txt 2>&1
    exit_code=$?
    set -e
    if [ "$exit_code" != "${expected_exit[$example]}" ]; then
        echo "::error::$example exited with $exit_code, expected ${expected_exit[$example]}"
        cat /tmp/doctest-output.txt
        status=1
        continue
    fi
    normalize < /tmp/doctest-output.txt > /tmp/doctest-normalized.txt
    if $update; then
        mkdir -p "$(dirname "$snapshot")"
        cp /tmp/doctest-normalized.txt "$snapshot"
        echo "updated $snapshot"
    elif ! diff -u "$snapshot" /tmp/doctest-normalized.txt; then
        echo "::error::output of $example differs from snapshot $snapshot"
        echo "(if the change is intentional, run: scripts/check-example-output.sh --update)"
        status=1
    else
        echo "$example output matches $snapshot"
    fi
done

exit "$status"
