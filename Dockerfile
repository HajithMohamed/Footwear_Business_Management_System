# ---------------------------------------------------------------------------
# Footwear Wholesale ERP — PHP 8.3 + Apache
# ---------------------------------------------------------------------------
FROM node:20-alpine AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY tailwind.config.js ./
COPY app/Views ./app/Views
COPY app/Helpers ./app/Helpers
COPY public/assets/css/tailwind.input.css ./public/assets/css/tailwind.input.css
RUN npm run build:css

FROM php:8.3-apache

# System libs + PHP extensions (GD for image processing, pdo_mysql for DB)
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg62-turbo-dev \
        libwebp-dev \
        libfreetype6-dev \
        tesseract-ocr \
        poppler-utils \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql \
    && a2enmod rewrite headers \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

# Recommended production PHP defaults + generous upload limits for photos/PDFs
RUN { \
        echo 'upload_max_filesize = 20M'; \
        echo 'post_max_size = 25M'; \
        echo 'memory_limit = 256M'; \
        echo 'expose_php = Off'; \
    } > /usr/local/etc/php/conf.d/zz-app.ini

# Apache virtual host (docroot -> /public, AllowOverride, uploads hardening)
COPY docker/vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY . .
COPY --from=assets /build/public/assets/css/tailwind.css /var/www/html/public/assets/css/tailwind.css

# Entrypoint writes .env from container env, fixes permissions, starts Apache
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p storage/logs storage/backups public/uploads \
    && chown -R www-data:www-data storage public/uploads

EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
