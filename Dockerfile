FROM node:20-alpine AS frontend

WORKDIR /build/frontend

COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend/ ./
RUN npm run build


FROM php:8.2-cli-bookworm AS vendor

WORKDIR /build/app

RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    libicu-dev \
    libzip-dev \
    unzip \
    git \
  && docker-php-ext-install intl zip \
  && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress


FROM php:8.2-fpm-bookworm AS web

WORKDIR /var/www

RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    libicu-dev \
    libzip-dev \
    unzip \
  && docker-php-ext-install intl mysqli pdo_mysql \
  && rm -rf /var/lib/apt/lists/*

# App code
COPY . /var/www

# PHP dependencies
COPY --from=vendor /build/app/vendor /var/www/vendor

# Built frontend (served by Nginx from CI4 public/)
COPY --from=frontend /build/frontend/dist/ /var/www/public/

# Nginx + supervisor
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh \
  && chown -R www-data:www-data /var/www/writable /var/www/public \
  && chmod -R 775 /var/www/writable

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
