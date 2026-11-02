#!/bin/sh
# =============================================================================
# entrypoint-web.sh — Prepara el estado escribible del contenedor `web`.
# =============================================================================
# MOTIVO
# ------
# `/var/www/html` es un BIND MOUNT del host (`./src:/var/www/html:z` en
# docker-compose.yml). Los permisos del host se ven tal cual dentro del
# contenedor: si `src/uploads/` en el host NO es escribible por el usuario con
# el que corre el worker de Apache, cada subida de PDF falla con:
#
#   PHP Warning: move_uploaded_file(.../uploads/archivo.pdf):
#     Failed to open stream: Permission denied
#
# y el endpoint POST /api/documentos responde 400. El resto de la app
# funciona (login, dashboard, listados), por eso el sintoma es desconcertante.
#
# POR QUE SE ARREGLA DENTRO DEL CONTENEDOR Y NO EN EL HOST
# -------------------------------------------------------
# El worker de Apache corre como `www-data` (uid 33 DENTRO del contenedor).
# En Docker rootful ese uid es el 33 del host, pero con Podman rootless (o
# Docker rootless) el uid del host es OTRO (un uid alto del subuid map).
# Por eso el unico lugar donde se puede calcular bien el owner es ADENTRO del
# contenedor, usando el NOMBRE `www-data`: el runtime lo resuelve solo.
# Un `chown 33:33` desde el host seria correcto en rootful y equivocado en
# rootless.
#
# Efecto secundario aceptado: los PDFs subidos quedan en el host con owner
# `www-data` (uid 33). Borrarlos a mano desde el host requiere `sudo`.
#
# IDEMPOTENCIA
# ------------
# El entrypoint corre en cada arranque del contenedor. Es seguro repetirlo:
# `install -d` y `chown` son operaciones idempotentes sobre el mismo estado.
# =============================================================================

set -e

UPLOADS_DIR="/var/www/html/uploads"

# --- 1. El directorio existe y tiene el owner correcto -----------------------
# `install -d` crea el directorio si falta y, si ya existe, le aplica el modo
# y el owner pedidos. Es la forma idiomática de "dejar esto como quiero".
if ! install -d -o www-data -g www-data -m 0775 "$UPLOADS_DIR" 2>/dev/null; then
    # Fallback: si `install` no puede fijar el owner (FS con root_squash,
    # por ejemplo), al menos intentamos crear el directorio y darle permisos.
    mkdir -p "$UPLOADS_DIR" 2>/dev/null || true
    chmod 0775 "$UPLOADS_DIR" 2>/dev/null || true
fi

# --- 2. Los archivos que ya quedaron adentro tambien son de www-data --------
# `chown -R` sobre un bind mount cambia el owner EN EL HOST. Es lo que
# queremos: los PDFs subidos por una corrida anterior pueden haber quedado
# con otro owner y el worker actual no los podria reemplazar.
if ! chown -R www-data:www-data "$UPLOADS_DIR" 2>/dev/null; then
    echo "[entrypoint] WARN: no se pudo hacer chown de ${UPLOADS_DIR} a www-data:www-data" >&2
    echo "[entrypoint] WARN: las subidas de archivos van a fallar con 'Permission denied'" >&2
    echo "[entrypoint] WARN: revisá con 'ls -ld ${UPLOADS_DIR}'" >&2
else
    echo "[entrypoint] uploads listo: $(ls -ld "$UPLOADS_DIR" 2>/dev/null | awk '{print $1, $3, $4}')"
fi

# --- 3. La app arranca como siempre -----------------------------------------
# `docker-php-entrypoint` es el entrypoint oficial de la imagen: se encarga
# de procesar los argumentos de PHP (php-fpm vs apache2-foreground) y de
# ejecutar el CMD. Encadenarlo con `exec` conserva el PID 1 y el manejo de
# senales (SIGTERM -> shutdown limpio de Apache).
exec docker-php-entrypoint "$@"
