#!/usr/bin/env bash
# scripts/lib/bd.sh
# Instalacion del cliente MariaDB (compatible con healthchecks del repo).
# Modulo: bd

set -euo pipefail
IFS=$'\n\t'

mod_bd_run() {
    logger_section "Módulo bd (cliente MariaDB)"

    if module_skip_if_done bd \
        'command -v mariadb >/dev/null && command -v mariadb-dump >/dev/null'; then
        return 0
    fi

    if ! command -v mariadb >/dev/null 2>&1; then
        logger_info "Instalando mariadb-client"
        run_cmd apt-get install -y -qq mariadb-client
    else
        logger_ok "mariadb-client ya instalado"
    fi

    local version
    version="$(mariadb --version 2>/dev/null || echo 'no disponible')"
    sb_kv "mariadb" "$version"
    sb_kv "capacidad SSL" "$(mariadb --ssl --help 2>/dev/null | grep -q -- '--ssl' && echo 'SI' || echo 'NO')"

    # No se hace ping real al server aqui — eso lo hace 'verificar' post-deploy.
    # El script bd solo deja la toolchain lista en el host.

    state_set_module_completed bd '{"client": "mariadb-client"}'
    logger_ok "Cliente MariaDB instalado"
}

mod_bd_status() {
    mariadb --version 2>/dev/null
}
