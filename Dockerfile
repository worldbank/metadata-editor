FROM php:8.2-apache

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        default-mysql-client \
        git \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libxml2-dev \
        libxslt1-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" curl gd mbstring mysqli opcache pcntl xsl zip \
    && a2enmod headers rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/dev.ini /usr/local/etc/php/conf.d/metadata-editor-dev.ini
COPY docker/php/docker-entrypoint.sh /usr/local/bin/metadata-editor-entrypoint

WORKDIR /var/www/html

COPY . /var/www/html

RUN chmod +x /usr/local/bin/metadata-editor-entrypoint \
    && mkdir -p \
        cache \
        datafiles/editor \
        datafiles/tmp/uploads \
        files/captcha \
        files/images \
        files/public \
        files/thumbnails \
        imports \
        logs \
    && chown -R www-data:www-data cache datafiles files imports logs

ENTRYPOINT ["metadata-editor-entrypoint"]
CMD ["apache2-foreground"]
