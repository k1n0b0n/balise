# The site for local development (dev) and production (production, with public/ from the app image of the same
# release, so that both serve the same built assets; ADR-0020 point 4).
ARG APP_IMAGE=balise-php:production

FROM ${APP_IMAGE} AS app


FROM nginx:1.30-alpine AS dev

RUN rm /etc/nginx/conf.d/default.conf

COPY docker/nginx/upstream-docker.conf /etc/nginx/conf.d/upstream.conf
COPY docker/nginx/site.conf /etc/nginx/conf.d/balise.conf


FROM dev AS production

COPY --from=app /var/www/balise/public /var/www/balise/public
