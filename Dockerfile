# syntax=docker/dockerfile:1
# Production image. One image runs the web app, the queue worker and the scheduler (see docker-compose.prod.yml).

FROM serversideup/php:8.4-fpm-nginx AS base
USER root
RUN install-php-extensions intl gd exif
USER www-data

FROM base AS vendor
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

FROM node:24-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor
RUN npm run build

FROM base AS app
COPY --chown=www-data:www-data --from=vendor /var/www/html/vendor ./vendor
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build
RUN composer dump-autoload --no-dev --optimize --no-interaction

# Coolify's proxy sits on a private Docker network; health checks hit Laravel's /up route.
ENV PHP_OPCACHE_ENABLE=1 \
    HEALTHCHECK_PATH=/up \
    TRUSTED_PROXY=local
