#!/bin/sh
# One-shot first-run setup, executed by the "setup" service before the PHP processes start.
set -e

composer install --no-interaction --no-progress
[ -d generated/Transit_realtime ] || sh scripts/generate-gtfs-realtime.sh
[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --no-interaction
php artisan migrate --force --no-interaction
php artisan boost:update --no-interaction --no-discover
