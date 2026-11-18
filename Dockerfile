# syntax=docker/dockerfile:1
# Images pinned by digest (Dependabot "docker" keeps them current), like the SHA-pinned actions.

# 1. Front-end assets. Install scripts stay off (.npmrc); only public/build leaves this stage.
FROM node:26-alpine@sha256:6bd9ab43c0ec0190ca7b2d193906cb8b7edf6e7f4afa43ddeccd6452710bffb5 AS assets
WORKDIR /src
COPY package.json package-lock.json .npmrc ./
RUN npm ci --ignore-scripts
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# 2. Runtime: FrankenPHP (Caddy + PHP 8.4 with pdo_sqlite, sodium, Argon2id).
FROM dunglas/frankenphp:1.9-php8.4-alpine@sha256:ef77236485e5b28a5f61519584d91f3ee603261ab56a4cf343624f2261e3054e
WORKDIR /app

COPY --from=composer:2@sha256:0c8e663f14c94d07b3c45b175c51f5a14718508f21fe4dd7e54f09488dd0ed71 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
COPY --from=assets /src/public/build ./public/build
# Composer is removed after use: nothing in the running image can install packages.
# setcap -r: port 8000 needs no cap_net_bind_service, so the container can run with cap_drop: ALL.
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
 && php artisan package:discover \
 && rm /usr/bin/composer \
 && apk add --no-cache --virtual .setcap libcap && setcap -r /usr/local/bin/frankenphp && apk del .setcap \
 && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && adduser -D -u 10001 app \
 && mkdir -p storage/app/private storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R app:app storage bootstrap/cache /data /config

USER app
# Plain http on 8000: Secure cookies work on http://localhost only. Put TLS in front for anything else.
ENV SERVER_NAME=:8000 \
    CADDY_GLOBAL_OPTIONS="admin off"
EXPOSE 8000
VOLUME /app/storage

# Fail closed: the container does not start with an insecure configuration.
CMD ["sh", "-c", "php artisan vault:check-config && touch storage/database.sqlite && php artisan migrate --force && exec frankenphp run --config /etc/frankenphp/Caddyfile"]
