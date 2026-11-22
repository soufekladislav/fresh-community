#!/usr/bin/env bash
# Build a WordPress update package without changing the installed plugin folder.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
HEADER_VERSION="$(sed -n 's/^ \* Version: //p' "$ROOT/ar-community.php" | head -n 1)"
RUNTIME_VERSION="$(sed -n "s/.*define('ARC_VERSION', '\([^']*\)').*/\1/p" "$ROOT/ar-community.php" | head -n 1)"
if [[ -z "$HEADER_VERSION" || "$HEADER_VERSION" != "$RUNTIME_VERSION" ]]; then
  echo "Plugin header version differs from ARC_VERSION." >&2
  exit 1
fi
OUT="${1:-$ROOT/dist}"
mkdir -p "$OUT"
OUT="$(cd "$OUT" && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
mkdir -p "$TMP/ar-community"
cp "$ROOT/ar-community.php" "$TMP/ar-community/"
cp -R "$ROOT/includes" "$ROOT/assets" "$ROOT/templates" "$TMP/ar-community/"
ZIP="$OUT/fresh-community-$HEADER_VERSION-update.zip"
(cd "$TMP" && zip -q -r "$ZIP" ar-community)
unzip -Z1 "$ZIP" | grep -q '^ar-community/ar-community.php$'
unzip -Z1 "$ZIP" | grep -q '^ar-community/includes/class-arc-config.php$'
echo "Update package created: $ZIP"
echo "Installed directory stays: ar-community/; no uninstall or database wipe."
