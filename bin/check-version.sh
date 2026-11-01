#!/usr/bin/env bash
#
# Confirms the plugin version is the same everywhere it is written.
#
# Usage:
#   bash bin/check-version.sh            check the files agree with each other
#   bash bin/check-version.sh 1.2.3      also check they match 1.2.3 (used for release tags)

set -euo pipefail
cd "$(dirname "$0")/.."

header=$(sed -nE 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*([0-9][^[:space:]]*).*/\1/p' seo-confident.php | head -1)
constant=$(sed -nE "s/^define\( 'SEOC_VERSION', '([^']+)' \);.*/\1/p" seo-confident.php | head -1)
stable=$(sed -nE 's/^Stable tag:[[:space:]]*([^[:space:]]+).*/\1/p' readme.txt | head -1)
readme_log=$(sed -nE 's/^= ([0-9][^ ]*) =[[:space:]]*$/\1/p' readme.txt | head -1)
changelog=$(sed -nE 's/^## \[([0-9][^]]*)\].*/\1/p' CHANGELOG.md | head -1)

printf '%-34s %s\n' \
    "Plugin header (seo-confident.php)" "${header:-missing}" \
    "SEOC_VERSION constant" "${constant:-missing}" \
    "Stable tag (readme.txt)" "${stable:-missing}" \
    "Latest readme.txt changelog entry" "${readme_log:-missing}" \
    "Latest CHANGELOG.md entry" "${changelog:-missing}"

expected="${1:-$header}"
status=0

for value in "$header" "$constant" "$stable" "$readme_log" "$changelog"; do
    if [ "$value" != "$expected" ]; then
        status=1
    fi
done

if [ "$status" -ne 0 ]; then
    echo "Version mismatch. Expected $expected everywhere." >&2
    exit 1
fi

echo "All versions match: $expected"
