#!/bin/sh
# Wrapper del entrypoint de zabbix-web.
# Aplica el fastcgi_param REMOTE_USER que la imagen oficial no incluye
# por defecto pero que es necesario para HTTP Authentication vía nginx.
#
# IMPORTANTE: parchear /etc/zabbix/nginx.conf (el original) y NO
# /etc/nginx/http.d/nginx.conf, porque el entrypoint oficial recrea
# este último como symlink que apunta al original, perdiendo cualquier
# cambio directo.

set -e

NGINX_CONF="/etc/zabbix/nginx.conf"

if [ -f "$NGINX_CONF" ] && ! grep -q "fastcgi_param REMOTE_USER" "$NGINX_CONF"; then
    sed -i 's|include fastcgi_params;|fastcgi_param REMOTE_USER $http_x_remote_user;\n        include fastcgi_params;|g' "$NGINX_CONF"
fi

# Continúa con el entrypoint original
exec /usr/bin/docker-entrypoint.sh "$@"
