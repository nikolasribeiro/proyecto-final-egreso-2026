# Generado por songbird-operator (issue #35). NO EDITAR A MANO.
# Para regenerar: sudo scripts/operator.sh --module deploy
#
# Este archivo es el .env final del proyecto. Sobrescribe los defaults
# literales del .env.example antes de hacer docker compose up -d.

APP_PORT={{APP_PORT}}
APP_ENVIRONMENT=production

MYSQL_DATABASE={{MYSQL_DATABASE}}
MYSQL_USER={{MYSQL_USER}}
MYSQL_PASSWORD={{MYSQL_PASSWORD}}
MYSQL_ROOT_PASSWORD={{MYSQL_ROOT_PASSWORD}}

# Credenciales Zabbix regeneradas automaticamente (evita los defaults
# literales 'cambiar_en_produccion' que rompen healthchecks).
ZABBIX_DB_NAME={{ZABBIX_DB_NAME}}
ZABBIX_DB_USER={{ZABBIX_DB_USER}}
ZABBIX_DB_PASSWORD={{ZABBIX_DB_PASSWORD}}
ZABBIX_DB_ROOT_PASSWORD={{ZABBIX_DB_ROOT_PASSWORD}}
ZABBIX_TZ={{ZABBIX_TZ}}
ZABBIX_SERVER_PORT={{ZABBIX_SERVER_PORT}}
ZABBIX_SERVER_IMAGE={{ZABBIX_SERVER_IMAGE}}
ZABBIX_WEB_IMAGE={{ZABBIX_WEB_IMAGE}}
ZABBIX_AGENT_IMAGE={{ZABBIX_AGENT_IMAGE}}
ZABBIX_DB_IMAGE={{ZABBIX_DB_IMAGE}}
ZABBIX_BASE_URL={{ZABBIX_BASE_URL}}
