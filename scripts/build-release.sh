#!/bin/sh
# Build a release archive for the deploy playbook (ADR-0004, ADR-0019): the committed HEAD, without dev packages,
# with built assets and generated classes, so that the server needs no Node, Composer or Git.
#   docker compose run --rm test sh scripts/build-release.sh    # writes build/balise-<UTC time>-<commit>.tar.gz
set -eu

# Uncommitted changes are left out on purpose: a release is a commit.
name="balise-$(date -u +%Y%m%d%H%M%S)-$(git rev-parse --short HEAD)"
out="$(pwd)/build"
work=$(mktemp -d)
# The archive's root becomes the release directory: mktemp's 0700 would keep nginx out.
chmod 755 "$work"
trap 'rm -rf "$work"' EXIT

git archive HEAD | tar -x -C "$work"
cd "$work"

# Generated first: Composer's optimised autoloader maps generated/.
sh scripts/generate-gtfs-realtime.sh
composer install --no-dev --optimize-autoloader --no-interaction --no-progress
npm ci --no-audit --no-fund
npm run build
rm -rf node_modules tests

mkdir -p "$out"
tar -czf "$out/$name.tar.gz" .
echo "$out/$name.tar.gz"
