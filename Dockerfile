FROM php:8.2-apache AS BASE

RUN docker-php-ext-install pdo_mysql

RUN a2enmod rewrite

# Añade esto en tu Dockerfile (si usas php:8.2-apache)
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    echo "upload_max_filesize = 50M" >> "$PHP_INI_DIR/php.ini" && \
    echo "post_max_size = 50M" >> "$PHP_INI_DIR/php.ini"