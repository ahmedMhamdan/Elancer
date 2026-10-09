# Elancer for Render's free plan: one container serves the site with Apache and PHP and, while it is
# awake, runs the queue worker and the scheduler beside it. Migrations are not run here; the runtime
# database role cannot change the schema.

FROM php:8.3-apache-bookworm AS base
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
# pdo_pgsql for the hosted database, gd for profile photos and portfolio images.
RUN install-php-extensions pdo_pgsql gd intl zip bcmath pcntl opcache exif
WORKDIR /var/www/html

# PHP dependencies without development packages.
FROM base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --no-progress --prefer-dist
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-interaction

# Frontend build. The Wayfinder plugin calls artisan while building, so this stage needs PHP and the
# vendor folder as well as Node. It reads routes only: a throwaway key and no database.
FROM vendor AS assets
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm
# The name shown in page titles is fixed into the built scripts.
ARG APP_NAME=Elancer
RUN cp .env.example .env \
    && php artisan key:generate --force --no-interaction \
    && npm ci --no-audit --no-fund \
    && VITE_APP_NAME="$APP_NAME" DB_CONNECTION=sqlite DB_DATABASE=:memory: CACHE_STORE=array SESSION_DRIVER=array npm run build \
    && rm .env

FROM base AS app
# Apache listens on the port Render assigns.
RUN a2enmod rewrite headers \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && sed -ri 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf
COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-elancer.ini
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY --from=vendor /var/www/html /var/www/html
COPY --from=assets /var/www/html/public/build /var/www/html/public/build
RUN chown -R www-data:www-data storage bootstrap/cache
ENV PORT=10000
EXPOSE 10000
CMD ["sh", "/var/www/html/docker/start.sh"]
