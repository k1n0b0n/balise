# One Dockerfile, three targets (ADR-0007, ADR-0020):
#   runtime  PHP 8.5 and the project's PHP configuration: queue, scheduler, and the base of production
#   dev      runtime + Composer and the tools of the setup scripts
#   test     dev + Node and Chromium for the browser tests and CI
FROM php:8.5-fpm-trixie AS runtime

# Extensions the official image lacks. Its compilers stay (Debian variant), only the headers go once built.
# phpredis from its GitHub release, checked by SHA-256: PECL's downloads time out too often for a build.
ARG PHPREDIS_VERSION=6.3.0
ARG PHPREDIS_SHA256=cb8f81df1a275599e4f8ddcfec7e1f65ed1953e6f5673649149fd680ebff4cad
RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu76 libpq5 libzip5 libicu-dev libpq-dev libzip-dev \
    && curl -fsSL -o /tmp/phpredis.tar.gz "https://github.com/phpredis/phpredis/archive/refs/tags/${PHPREDIS_VERSION}.tar.gz" \
    && echo "${PHPREDIS_SHA256}  /tmp/phpredis.tar.gz" | sha256sum -c - \
    && docker-php-source extract \
    && mkdir /usr/src/php/ext/redis \
    && tar -xzf /tmp/phpredis.tar.gz -C /usr/src/php/ext/redis --strip-components=1 \
    && docker-php-ext-install -j"$(nproc)" bcmath intl pdo_pgsql pgsql redis sockets zip \
    && docker-php-source delete \
    && rm /tmp/phpredis.tar.gz \
    && apt-get purge -y --auto-remove libicu-dev libpq-dev libzip-dev \
    && rm -rf /var/lib/apt/lists/* \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && useradd --create-home --uid 1000 balise

COPY docker/php/php.ini "$PHP_INI_DIR/conf.d/99-balise.ini"
COPY docker/php/fpm-docker.conf /usr/local/etc/php-fpm.d/zz-balise.conf

WORKDIR /var/www/balise

USER balise


FROM runtime AS dev

USER root

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip git \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

USER balise


FROM dev AS test

USER root

# Node from its official image, on the same Debian release as PHP's.
COPY --from=node:24-trixie-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:24-trixie-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

# Chromium must match the exact Playwright version of package.json: rebuild this target when it changes.
COPY package.json /tmp/package.json
ENV PLAYWRIGHT_BROWSERS_PATH=/opt/playwright
RUN apt-get update \
    && npx -y "playwright@$(node -p "require('/tmp/package.json').devDependencies.playwright")" install --with-deps chromium \
    && chmod -R a+rX /opt/playwright \
    && rm -rf /var/lib/apt/lists/* /tmp/package.json /root/.npm

USER balise

CMD ["composer", "qa"]
