FROM ubuntu:26.04

ENV DEBIAN_FRONTEND=noninteractive

COPY docker/php/packages.txt /tmp/packages.txt

# PHP 8.5 comes from the Ubuntu 26.04 archive; ppa:ondrej/php has no 26.04 build.
RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl unzip git \
    && xargs -a /tmp/packages.txt apt-get install -y --no-install-recommends \
    && rm -rf /var/lib/apt/lists/* /tmp/packages.txt \
    && mkdir -p /run/php \
    && chown ubuntu:ubuntu /run/php

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /etc/php/8.5/fpm/conf.d/99-balise.ini
COPY docker/php/php.ini /etc/php/8.5/cli/conf.d/99-balise.ini
COPY docker/php/fpm-docker.conf /etc/php/8.5/fpm/pool.d/zz-docker.conf

WORKDIR /var/www/balise

USER ubuntu

CMD ["php-fpm8.5", "--nodaemonize"]
