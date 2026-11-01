#!/usr/bin/env bash
#
# Builds dist/seo-confident-<version>.zip, ready to upload to WordPress.org or a site.
#
# The zip is made from the last commit with git archive, so development files
# marked export-ignore in .gitattributes stay out. Commit your changes first.
#
# Usage: bash bin/build-zip.sh

set -euo pipefail
cd "$(dirname "$0")/.."

slug="seo-confident"
version=$(sed -nE 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*([0-9][^[:space:]]*).*/\1/p' seo-confident.php | head -1)

if [ -z "$version" ]; then
    echo "Could not read the version from seo-confident.php." >&2
    exit 1
fi

if [ -n "$(git status --porcelain)" ]; then
    echo "Warning: uncommitted changes are not included in the zip." >&2
fi

mkdir -p dist
out="dist/${slug}-${version}.zip"
rm -f "$out"

git archive --format=zip --prefix="${slug}/" --output="$out" HEAD

echo "Built $out"
