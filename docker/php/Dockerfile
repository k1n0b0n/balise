# One Dockerfile, four targets (ADR-0007, ADR-0020 points 2 and 4):
#   runtime     PHP 8.5 on Alpine and the project's PHP configuration: queue, scheduler, and the base of production
#   dev         runtime + Composer and the tools of the setup scripts
#   test        PHP 8.5 on Debian + Composer, Node and Chromium, for the browser tests: Playwright's browsers need glibc
#   production  runtime + the code of a release, without dev packages, with built assets and generated classes
ARG NODE_VERSION=24

FROM node:${NODE_VERSION}-trixie-slim AS node-debian

FROM node:${NODE_VERSION}-alpine AS node-alpine


FROM php:8.5-fpm-alpine AS runtime

COPY docker/php/install-extensions.sh /tmp/install-extensions.sh
RUN sh /tmp/install-extensions.sh \
    && rm /tmp/install-extensions.sh \
    && adduser -D -u 1000 balise

COPY docker/php/php.ini "$PHP_INI_DIR/conf.d/99-balise.ini"
COPY docker/php/fpm-docker.conf /usr/local/etc/php-fpm.d/zz-balise.conf

WORKDIR /var/www/balise

USER balise


FROM runtime AS dev

USER root

RUN apk add --no-cache git unzip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

USER balise


# Built in one stage, then flattened: the compilers the Debian image keeps would stay in its layers otherwise.
FROM php:8.5-cli-trixie AS test-build

COPY docker/php/install-extensions.sh /tmp/install-extensions.sh
RUN sh /tmp/install-extensions.sh \
    && rm /tmp/install-extensions.sh \
    && useradd --create-home --uid 1000 balise

COPY docker/php/php.ini "$PHP_INI_DIR/conf.d/99-balise.ini"
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node from its official image, on the same Debian release as PHP's.
COPY --from=node-debian /usr/local/bin/node /usr/local/bin/node
COPY --from=node-debian /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

# Chromium must match the exact Playwright version of package.json: rebuild this target when it changes.
# Only the headless shell: tests never open a window, and the full Chromium would add 400 MB.
# One font is enough for a French page in system fonts (ADR-0008); Playwright installs scripts from CJK to Thai.
# The shell links libgbm, whose package pulls Mesa and LLVM (180 MB), but it draws with its own SwiftShader:
# Mesa goes, by dpkg since apt would take libgbm with it. No apt runs after this stage.
COPY package.json /tmp/package.json
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && PLAYWRIGHT_BROWSERS_PATH=/opt/playwright npx -y "playwright@$(node -p "require('/tmp/package.json').devDependencies.playwright")" install --with-deps --only-shell chromium \
    && chmod -R a+rX /opt/playwright \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS libc6-dev fonts-freefont-ttf fonts-ipafont-gothic fonts-noto-color-emoji fonts-tlwg-loma-otf fonts-unifont fonts-wqy-zenhei \
    && dpkg --remove --force-depends $(dpkg-query -Wf '${Package}\n' 'libgl1-mesa-dri' 'libglx-mesa0' 'mesa-libgallium' 'libllvm*' 'libz3-*' 2>/dev/null) \
    && rm -rf /var/lib/apt/lists/* /usr/src/* /tmp/package.json /root/.npm


FROM scratch AS test

COPY --from=test-build / /

ENV PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin \
    PHP_INI_DIR=/usr/local/etc/php \
    PLAYWRIGHT_BROWSERS_PATH=/opt/playwright

WORKDIR /var/www/balise

USER balise

CMD ["composer", "qa"]


# The code of a release, as a commit has it; generated classes first, since Composer's optimised autoloader maps them.
FROM dev AS build

USER root

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY . .
RUN sh scripts/generate-gtfs-realtime.sh \
    && composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Vite bundles Livewire's script from vendor/.
FROM node-alpine AS assets

WORKDIR /var/www/balise

COPY --from=build /var/www/balise ./
RUN npm ci --no-audit --no-fund \
    && npm run build


# The code belongs to root and only storage/ and bootstrap/cache/ to balise: a compromised PHP process cannot
# rewrite the application. The labels tell the deploy which PostgreSQL and Redis majors this release expects.
FROM runtime AS production

ARG POSTGRESQL_VERSION
ARG REDIS_VERSION

USER root

COPY --from=build /var/www/balise ./
COPY --from=assets /var/www/balise/public/build public/build
COPY docker/php/production-entrypoint.sh /usr/local/bin/balise-entrypoint
RUN for major in "$POSTGRESQL_VERSION" "$REDIS_VERSION"; do \
        case "$major" in '' | *[!0-9]*) echo "POSTGRESQL_VERSION and REDIS_VERSION must be majors" >&2; exit 1 ;; esac; \
    done \
    && rm -rf .coderabbit.yaml .github .mcp.json ansible docker docs tests \
    && chown -R balise:balise storage bootstrap/cache \
    && chmod 755 /usr/local/bin/balise-entrypoint

LABEL balise.postgresql="${POSTGRESQL_VERSION}" \
      balise.redis="${REDIS_VERSION}"

USER balise

ENTRYPOINT ["balise-entrypoint"]
CMD ["php-fpm"]
