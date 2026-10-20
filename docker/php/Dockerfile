# One Dockerfile, three targets (ADR-0007):
#   runtime  what the server runs: PHP 8.5 and the project's PHP configuration
#   dev      runtime + Composer and the tools of the setup scripts
#   test     dev + Node and Chromium for the browser tests
FROM ubuntu:26.04 AS runtime

ENV DEBIAN_FRONTEND=noninteractive

COPY docker/php/packages.txt /tmp/packages.txt

# PHP 8.5 comes from the Ubuntu 26.04 archive; ppa:ondrej/php has no 26.04 build.
RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates \
    && xargs -a /tmp/packages.txt apt-get install -y --no-install-recommends \
    && rm -rf /var/lib/apt/lists/* /tmp/packages.txt \
    && mkdir -p /run/php \
    && chown ubuntu:ubuntu /run/php

COPY docker/php/php.ini /etc/php/8.5/fpm/conf.d/99-balise.ini
COPY docker/php/php.ini /etc/php/8.5/cli/conf.d/99-balise.ini
COPY docker/php/fpm-docker.conf /etc/php/8.5/fpm/pool.d/zz-docker.conf

WORKDIR /var/www/balise

USER ubuntu

CMD ["php-fpm8.5", "--nodaemonize"]


FROM runtime AS dev

USER root

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl unzip git \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

USER ubuntu


FROM dev AS test

USER root

# Node from its official image: the Ubuntu archive has an older major than Vite and CI (versions.yaml).
COPY --from=node:24-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:24-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

# Chromium must match the exact Playwright version of package.json: rebuild this target when it changes.
COPY package.json /tmp/package.json
ENV PLAYWRIGHT_BROWSERS_PATH=/opt/playwright
RUN apt-get update \
    && npx -y "playwright@$(node -p "require('/tmp/package.json').devDependencies.playwright")" install --with-deps chromium \
    && chmod -R a+rX /opt/playwright \
    && rm -rf /var/lib/apt/lists/* /tmp/package.json /root/.npm

USER ubuntu

CMD ["composer", "qa"]
