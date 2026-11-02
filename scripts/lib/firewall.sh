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

    # Puerto SSH de la SESION ACTUAL. El modulo `ssh` mueve el daemon al puerto
    # ${SB_SSH_PORT} y el firewall aplica `default deny incoming`: si el
    # operador entro por otro puerto (22 por defecto) se cortaba su propia
    # sesion a mitad del deploy. Se permite ademas el puerto en uso.
    local session_ssh_port=""
    if [[ -n "${SSH_CONNECTION:-}" ]]; then
        # SSH_CONNECTION = "ip_cliente puerto_cliente ip_servidor puerto_servidor"
        session_ssh_port="${SSH_CONNECTION##* }"
    fi
    if [[ -n "$session_ssh_port" && "$session_ssh_port" != "$SB_SSH_PORT" ]] \
        && [[ "$session_ssh_port" =~ ^[0-9]+$ ]]; then
        run_cmd ufw allow "${session_ssh_port}/tcp" comment 'songbird-ssh-sesion-activa'
        logger_info "Puerto SSH de la sesion actual (${session_ssh_port}) permitido para no cortar la sesion"
    fi

    run_cmd ufw allow "${SB_APP_PORT}/tcp" comment 'songbird-app'
    
    # Se aplican valores por defecto si la variable no fue definida en el orquestador
    run_cmd ufw allow "${SB_ZABBIX_AGENT_PORT:-10050}/tcp" comment 'zabbix-agent'
    run_cmd ufw allow "${SB_ZABBIX_SERVER_PORT:-10051}/tcp" comment 'zabbix-server'

    if [[ "${SB_EXPOSE_ZABBIX_WEB:-false}" == "true" ]]; then
        run_cmd ufw allow "${SB_ZABBIX_WEB_PORT:-8080}/tcp" comment 'zabbix-web-public'
    fi

    # 5. Habilitar
    run_cmd ufw --force enable

    # 6. Verificar
    logger_info "Estado final de UFW:"
    run_cmd ufw status verbose

    # Verificar que las reglas críticas estén (Corregido orden de lectura e idioma)
    local ssh_app_rules
    ssh_app_rules="$(ufw status 2>/dev/null | grep -cE "^(${SB_SSH_PORT}|${SB_APP_PORT})/tcp" || true)"
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
