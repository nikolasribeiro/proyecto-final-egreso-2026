#!/usr/bin/env bash
# scripts/lib/firewall.sh
# Configuracion de UFW con default deny + puertos SSH/app/Zabbix.
# Modulo: firewall

set -euo pipefail
IFS=$'\n\t'

mod_firewall_run() {
    logger_section "Módulo firewall (UFW)"

    if module_skip_if_done firewall \
        'command -v ufw >/dev/null && ufw status 2>/dev/null | grep -q "Status: active"'; then
        return 0
    fi

    # 1. Instalar UFW
    if ! command -v ufw >/dev/null 2>&1; then
        logger_info "Instalando ufw"
        run_cmd apt-get install -y -qq ufw
    else
        logger_ok "ufw ya instalado"
    fi

    # 2. Reset a estado conocido
    run_cmd ufw --force reset >/dev/null

    # 3. Defaults
    run_cmd ufw default deny incoming
    run_cmd ufw default allow outgoing

    # 4. Reglas
    run_cmd ufw allow "${SB_SSH_PORT}/tcp" comment 'songbird-ssh'
    run_cmd ufw allow "${SB_APP_PORT}/tcp" comment 'songbird-app'
    run_cmd ufw allow "${SB_ZABBIX_AGENT_PORT}/tcp" comment 'zabbix-agent'
    run_cmd ufw allow "${SB_ZABBIX_SERVER_PORT}/tcp" comment 'zabbix-server'

    if [[ "$SB_EXPOSE_ZABBIX_WEB" == "true" ]]; then
        run_cmd ufw allow "${SB_ZABBIX_WEB_PORT}/tcp" comment 'zabbix-web-public'
    fi

    # 5. Habilitar
    run_cmd ufw --force enable

    # 6. Verificar
    logger_info "Estado final de UFW:"
    run_cmd ufw status verbose

    # Verificar que las reglas críticas estén
    local ssh_app_rules
    ssh_app_rules="$(ufw status 2>/dev/null | grep -cE "ALLOW IN.*(${SB_SSH_PORT}|${SB_APP_PORT})" || true)"
    if [[ "$ssh_app_rules" -lt 2 ]]; then
        logger_error "Reglas UFW para SSH/app no detectadas"
        return 70
    fi

    state_set_module_completed firewall \
        '{"ssh_port": '"${SB_SSH_PORT}"', "app_port": '"${SB_APP_PORT}"'}'
    logger_ok "Firewall configurado"
}

mod_firewall_status() {
    ufw status 2>/dev/null | head -n5 || echo "ufw no disponible"
}
