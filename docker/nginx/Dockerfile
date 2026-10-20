FROM nginx:1.30-alpine

RUN rm /etc/nginx/conf.d/default.conf

COPY docker/nginx/upstream-docker.conf /etc/nginx/conf.d/upstream.conf
COPY docker/nginx/site.conf /etc/nginx/conf.d/balise.conf
