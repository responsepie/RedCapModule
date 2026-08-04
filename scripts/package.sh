#!/usr/bin/env sh
set -eu

VERSION='0.1.0'
MODULE="response_pie_redcap_v${VERSION}"
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
DIST="$ROOT/dist"
STAGE=$(mktemp -d "${TMPDIR:-/tmp}/response-pie-package.XXXXXX")

cleanup() {
    rm -rf "$STAGE"
}
trap cleanup EXIT HUP INT TERM

mkdir -p "$DIST" "$STAGE/$MODULE"
for file in config.json ResponsePieRedcap.php README.md LICENSE; do
    cp "$ROOT/$file" "$STAGE/$MODULE/$file"
done

rm -f "$DIST/$MODULE.zip"
(
    cd "$STAGE"
    TZ=UTC find "$MODULE" -exec touch -t 202601010000 {} +
    zip -X -q "$DIST/$MODULE.zip" \
        "$MODULE/" \
        "$MODULE/config.json" \
        "$MODULE/ResponsePieRedcap.php" \
        "$MODULE/README.md" \
        "$MODULE/LICENSE"
)

printf 'Created %s\n' "$DIST/$MODULE.zip"
