# ── Stage 1: Composer dependencies ────────────────────────────────────────────
FROM composer:2 AS deps
WORKDIR /app
COPY composer*.json ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# ── Stage 2: Runtime (Apache + PHP 8.1) ───────────────────────────────────────
FROM php:8.1-apache

RUN apt-get update -qq \
 && apt-get install -y -qq curl \
 && docker-php-ext-install pdo_mysql \
 && a2enmod rewrite \
 && rm -rf /var/lib/apt/lists/*

# Point Apache DocumentRoot → public/
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/*.conf \
        /etc/apache2/apache2.conf \
        /etc/apache2/conf-available/*.conf

# Allow .htaccess overrides
RUN sed -ri 's/AllowOverride None/AllowOverride All/g' \
        /etc/apache2/apache2.conf \
        /etc/apache2/conf-available/*.conf 2>/dev/null || true

WORKDIR /var/www/html

COPY --from=deps /app/vendor ./vendor
COPY . .

RUN mkdir -p logs \
 && chown -R www-data:www-data logs public

ENV APP_ENV=production
EXPOSE 80

HEALTHCHECK --interval=30s --timeout=10s --start-period=25s --retries=3 \
  CMD curl -f http://localhost/health || exit 1
