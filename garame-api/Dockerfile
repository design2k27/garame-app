ARG APP_ENV=prod
ARG APP_DEBUG=0


FROM dunglas/frankenphp:1-php8.4-alpine AS builder

ARG APP_ENV
ARG APP_DEBUG

RUN apk add --no-cache git unzip \
    && install-php-extensions \
        pdo_pgsql \
        intl \
        zip

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./
RUN if [ "$APP_ENV" = "dev" ]; then \
        composer install \
            --no-interaction \
            --prefer-dist \
            --no-progress \
            --no-scripts; \
    else \
        composer install \
            --no-dev \
            --no-interaction \
            --prefer-dist \
            --no-progress \
            --no-scripts \
            --optimize-autoloader \
            --classmap-authoritative; \
    fi

COPY . .

RUN rm -rf var/cache/* var/log/* \
    && if [ "$APP_ENV" = "dev" ]; then \
        composer dump-autoload --optimize; \
    else \
        composer dump-autoload --no-dev --classmap-authoritative --optimize; \
    fi \
    && php bin/console importmap:require @hotwired/stimulus --no-interaction --env="$APP_ENV"

FROM dunglas/frankenphp:1-php8.4-alpine AS runtime

ARG APP_ENV
ARG APP_DEBUG

ENV APP_ENV=${APP_ENV} \
    APP_DEBUG=${APP_DEBUG} \
    SERVER_NAME=:80 \
    XDG_CONFIG_HOME=/tmp/caddy/config \
    XDG_DATA_HOME=/tmp/caddy/data

WORKDIR /app

RUN apk add --no-cache postgresql-client icu-data-full openssl \
    && install-php-extensions \
        pdo_pgsql \
        intl \
        zip \
        apcu

RUN if [ "$APP_ENV" = "dev" ] || [ "$APP_DEBUG" = "1" ]; then \
        mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"; \
    else \
        mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"; \
    fi

COPY docker/php/opcache.ini "$PHP_INI_DIR/conf.d/99-opcache.ini"
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

COPY --from=builder /app /app

RUN mkdir -p var/cache var/log /tmp/caddy/config/caddy /tmp/caddy/data/caddy/locks \
    && chown -R www-data:www-data /app /tmp/caddy

USER www-data

EXPOSE 80
EXPOSE 443

ENTRYPOINT ["/usr/local/bin/app-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
