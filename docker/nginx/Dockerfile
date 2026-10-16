FROM ubuntu:26.04

RUN apt-get update \
    && apt-get install -y --no-install-recommends nginx \
    && rm -rf /var/lib/apt/lists/* /etc/nginx/sites-enabled/default \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log

COPY docker/nginx/upstream-docker.conf /etc/nginx/conf.d/upstream.conf
COPY docker/nginx/site.conf /etc/nginx/sites-enabled/balise.conf

CMD ["nginx", "-g", "daemon off;"]
