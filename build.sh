#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

PLUGIN_DIR="msp-google-reviews"
PLUGIN_FILE="$PLUGIN_DIR/msp-google-reviews.php"

VERSION=$(grep -m1 "Version:" "$PLUGIN_FILE" | sed -E 's/.*Version:[[:space:]]*([0-9A-Za-z.\-]+).*/\1/')

if [ -z "$VERSION" ]; then
  echo "Could not determine plugin version from $PLUGIN_FILE" >&2
  exit 1
fi

BUILD_DIR="build/$PLUGIN_DIR"
ZIP_NAME="msp-google-reviews-v${VERSION}.zip"

rm -rf "build"
mkdir -p "$BUILD_DIR"

cp -r "$PLUGIN_DIR/." "$BUILD_DIR/"
find "$BUILD_DIR" -name ".DS_Store" -delete
rm -rf "$BUILD_DIR/.git"

rm -f "$ZIP_NAME"
(cd "build" && zip -r -q "../$ZIP_NAME" "$PLUGIN_DIR")

rm -rf "build"

echo "Built $ZIP_NAME"
