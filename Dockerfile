FROM php:8.2-apache AS BASE

RUN docker-php-ext-install pdo_mysql

RUN a2enmod rewrite

# Límites de subida de archivos.
#
#   upload_max_filesize = peso máximo del archivo (50 MB)
#   post_max_size        = peso máximo del POST completo. Tiene que ser MAYOR
#                          que upload_max_filesize porque incluye el overhead
#                          del multipart (boundary, headers). Con 52M hay
#                          margen para un PDF de 50M.
#
# Estos valores tienen que coincidir con el `client_max_body_size` de nginx y
# con el helper PHP `tamano_maximo_subida()` que le pasa el límite al
# frontend. Ver nginx/default.conf y src/Nucleo/ayudantes.php.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    echo "upload_max_filesize = 50M" >> "$PHP_INI_DIR/php.ini" && \
    echo "post_max_size = 52M" >> "$PHP_INI_DIR/php.ini"

# =============================================================================
# ENTRYPOINT: prepara /var/www/html/uploads antes de arrancar Apache.
# =============================================================================
# /var/www/html es un bind mount del host (./src). El worker de Apache escribe
# los PDFs subidos ahí como `www-data`; si el host no le da permiso de
# escritura, POST /api/documentos devuelve 400 ("Permission denied") mientras
# el resto de la app funciona normal.
#
# El chmod/chown se hace DENTRO del contenedor a proposito: es el unico lugar
# donde el uid de `www-data` es el correcto (funciona igual con Docker
# rootful que con Podman rootless). Ver docker/entrypoint-web.sh.
# =============================================================================
COPY docker/entrypoint-web.sh /usr/local/bin/entrypoint-web
RUN chmod +x /usr/local/bin/entrypoint-web

ENTRYPOINT ["/usr/local/bin/entrypoint-web"]
CMD ["apache2-foreground"]
