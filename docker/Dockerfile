FROM php:8.2-apache

# public/ ist der Webroot, genau wie auf dem all-inkl-Webspace.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

# gd + exif für die Galerie-Bildverarbeitung (Thumbnails, Aufnahmezeit).
# imagick bewusst NICHT – auf all-inkl unter PHP 8 nicht verfügbar; für HEIC
# nutzt der Code dort das ImageMagick-Binary (`convert`), hier libmagickwand.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev \
        imagemagick \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install pdo_mysql zip gd exif \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers \
    && sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf

COPY docker/apache-allowoverride.conf /etc/apache2/conf-available/grueze-allowoverride.conf
RUN a2enconf grueze-allowoverride

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-grueze.ini

WORKDIR /var/www/html
