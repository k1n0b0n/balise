#!/bin/sh
# Builds the PHP extensions the official images lack, on Alpine (runtime, dev) or Debian (test), ADR-0020 point 2.
# Only the libraries the extensions load stay; headers, sources and, on Alpine, the compilers go.
set -eux

EXTENSIONS="bcmath intl pdo_pgsql pgsql redis sockets zip"
# From its GitHub release, checked by SHA-256: PECL's downloads time out too often for a build.
PHPREDIS_VERSION=6.3.0
PHPREDIS_SHA256=cb8f81df1a275599e4f8ddcfec7e1f65ed1953e6f5673649149fd680ebff4cad

if command -v apk > /dev/null; then
    apk add --no-cache icu-libs libpq libzip
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libpq-dev libzip-dev linux-headers
else
    apt-get update
    apt-get install -y --no-install-recommends libicu76 libpq5 libzip5 libicu-dev libpq-dev libzip-dev
fi

curl -fsSL -o /tmp/phpredis.tar.gz "https://github.com/phpredis/phpredis/archive/refs/tags/${PHPREDIS_VERSION}.tar.gz"
echo "${PHPREDIS_SHA256}  /tmp/phpredis.tar.gz" | sha256sum -c -
docker-php-source extract
mkdir /usr/src/php/ext/redis
tar -xzf /tmp/phpredis.tar.gz -C /usr/src/php/ext/redis --strip-components=1
docker-php-ext-install -j"$(nproc)" $EXTENSIONS
docker-php-source delete
rm /tmp/phpredis.tar.gz

if command -v apk > /dev/null; then
    apk del .build-deps
else
    apt-get purge -y --auto-remove libicu-dev libpq-dev libzip-dev
    rm -rf /var/lib/apt/lists/*
fi

cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
