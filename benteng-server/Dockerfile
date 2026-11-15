# BENTENG untuk Coolify (atau Docker biasa): PHP 8.5 + Apache dalam satu container.
# Build tampilan (Vite) dikerjakan di tahap terpisah, jadi tidak perlu `npm run build` di laptop.
# Data SQLite disimpan di /data; pasang /data sebagai penyimpanan permanen di Coolify.

# --- Tahap 1: build tampilan -----------------------------------------------------
FROM node:22-alpine AS tampilan
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN npm run build

# --- Tahap 2: aplikasi -----------------------------------------------------------
FROM php:8.5-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev \
    && docker-php-ext-install intl zip \
    && rm -rf /var/lib/apt/lists/*

# Apache melayani folder public Laravel dan membaca .htaccess-nya.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel \
    && a2enmod rewrite headers \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependensi PHP dulu agar lapisan ini di-cache selama composer.lock tidak berubah.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --no-progress --prefer-dist

COPY . .
COPY --from=tampilan /app/public/build ./public/build

RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache /data \
    && composer dump-autoload --optimize --no-dev --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache /data \
    && chmod +x docker/mulai.sh

# Bawaan siap pakai untuk mode demo; semuanya bisa ditimpa lewat Environment Variables di Coolify.
# APP_KEY dibuat otomatis oleh docker/mulai.sh dan disimpan di /data.
ENV APP_NAME=BENTENG \
    APP_ENV=production \
    APP_DEBUG=false \
    APP_LOCALE=id \
    LOG_CHANNEL=stderr \
    LOG_LEVEL=warning \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/database.sqlite \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    QUEUE_CONNECTION=sync \
    TRUSTED_PROXIES=* \
    BENTENG_MODE_DEMO=true

EXPOSE 80
CMD ["docker/mulai.sh"]
