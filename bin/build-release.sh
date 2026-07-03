#!/usr/bin/env bash
set -euo pipefail

# Builds a clean, ready-to-upload WordPress plugin ZIP from the current working tree.
#
# Usage: bin/build-release.sh
#
# Reads the version from the plugin header, builds the Gutenberg block assets,
# assembles only the runtime files WordPress needs into dist/<slug>/, and zips
# the result to dist/<slug>-<version>.zip for manual testing via
# WP Admin > Plugins > Add New > Upload Plugin.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

SLUG="transcribus-viewer-for-wordpress"
MAIN_FILE="${SLUG}.php"

VERSION="$(grep -m1 '^ \* Version:' "$MAIN_FILE" | sed -E 's/.*Version:[[:space:]]*//')"
if [ -z "$VERSION" ]; then
    echo "Could not determine plugin version from $MAIN_FILE" >&2
    exit 1
fi

echo "Building release for ${SLUG} v${VERSION}..."

if [ ! -d node_modules ]; then
    echo "node_modules not found - running npm install first..."
    npm install
fi
npm run build

STAGING_DIR="dist/${SLUG}"
ZIP_NAME="${SLUG}-${VERSION}.zip"

# Only clear the staging subdirectory, never the rest of dist/ - older release
# zips are kept around intentionally so past versions stay available for testing.
rm -rf "$STAGING_DIR"
mkdir -p "$STAGING_DIR"

# Only the files WordPress needs at runtime - no src/, node_modules, dev tooling, or VCS files.
INCLUDE_PATHS=(
    "$MAIN_FILE"
    "uninstall.php"
    "readme.txt"
    "includes"
    "templates"
    "assets"
    "build"
)

for path in "${INCLUDE_PATHS[@]}"; do
    if [ -e "$path" ]; then
        cp -R "$path" "$STAGING_DIR/"
    fi
done

# Strip stray OS/editor files that may have been copied along with a directory.
find "$STAGING_DIR" -name ".DS_Store" -delete

# Remove only this version's zip if rebuilding it, so it doesn't inherit stale
# entries from a previous build of the same version - other versions are untouched.
rm -f "dist/${ZIP_NAME}"
( cd dist && zip -rq "$ZIP_NAME" "$SLUG" )
rm -rf "$STAGING_DIR"

echo "Release package ready: dist/${ZIP_NAME}"
echo "Upload via WP Admin > Plugins > Add New > Upload Plugin."
