#!/bin/sh
# Entry point of the production image: .env only exists on the host, so Laravel's caches are built
# when the container starts, then the official entry point runs the command (ADR-0020 point 4).
set -e

php artisan optimize --no-interaction > /dev/null
exec docker-php-entrypoint "$@"
