FROM dunglas/frankenphp:1-php8.5-trixie

RUN install-php-extensions pdo_pgsql intl mbstring dom xml zip opcache @composer
WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1
ARG APP_BUILD_ENV=dev
COPY composer.json composer.lock symfony.lock ./
RUN if [ "$APP_BUILD_ENV" = "prod" ]; then \
      composer install --no-interaction --no-dev --no-scripts --prefer-dist --no-progress --optimize-autoloader; \
    else \
      composer install --no-interaction --no-scripts --prefer-dist --no-progress; \
    fi
COPY . .
RUN composer dump-autoload --no-scripts --optimize
COPY docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
RUN mkdir -p /app/var/cache /app/var/log /app/var/evidence \
    && chown -R www-data:www-data /app/var

# Par defaut, mode classique (pas de FRANKENPHP_CONFIG=worker).
EXPOSE 80 443 443/udp
