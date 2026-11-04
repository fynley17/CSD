#!/usr/bin/env bash
# Builds build/plesk/ and build/revisionhub-plesk.zip, ready to upload to Plesk's httpdocs folder.
# Plesk's document root must then be set to httpdocs/public (see deploy/plesk/README.md).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/build/plesk"

rm -rf "$OUT" "$ROOT/build/revisionhub-plesk.zip"
mkdir -p "$OUT/public"

echo "Building the Vue frontend..."
(cd "$ROOT/frontend" && npm ci && npm run build)

echo "Copying the PHP backend..."
cp -R "$ROOT/backend/app" "$ROOT/backend/config" "$ROOT/backend/resources" "$OUT/"
cp "$ROOT/backend/routes.php" "$ROOT/backend/composer.json" "$ROOT/backend/composer.lock" "$OUT/"
# the local database password must never be uploaded, the server gets its own config/.env
rm -f "$OUT/config/.env"

echo "Putting the frontend and API entry point in public/..."
cp "$ROOT/backend/public/index.php" "$OUT/public/"
cp -R "$ROOT/frontend/dist/." "$OUT/public/"
cp "$ROOT/deploy/plesk/.htaccess" "$OUT/public/"

echo "Installing PHP packages without the dev tools..."
composer install --working-dir="$OUT" --no-dev --optimize-autoloader --no-interaction --quiet

(cd "$OUT" && zip -qr "$ROOT/build/revisionhub-plesk.zip" . -x '.DS_Store')
echo "Done: build/revisionhub-plesk.zip"
