#!/usr/bin/env bash
# scripts/lib/verificar.sh
# Smoke test de 15 checks sobre el estado del host + el stack.
# Modulo: verificar

set -euo pipefail
IFS=$'\n\t'

COMPOSE_FILE="${SB_REPO_DIR}/docker-compose.prod.yml"
ENV_FILE="${SB_REPO_DIR}/.env"

# Resultado del check: $1=numero, $2=etiqueta, $3=0=ok, $4=detalle
_record() {
    local n="$1" label="$2" code="$3" detail="${4:-}"
    if [[ "$code" -eq 0 ]]; then
        printf '  %s[ OK ]%s  %2d. %s\n' "$C_GREEN" "$C_RESET" "$n" "$label" >&2
    else
        printf '  %s[FAIL]%s  %2d. %s — %s\n' "$C_RED" "$C_RESET" "$n" "$label" "$detail" >&2
        SB_FAIL_COUNT=$((SB_FAIL_COUNT + 1))
    fi
}

SB_FAIL_COUNT=0

mod_verificar_run() {
    logger_section "Smoke test (15 checks)"

    SB_FAIL_COUNT=0

    # Cargar .env para variables usadas en los pings
    local mysql_root_pwd zbx_root_pwd
    if [[ -f "$ENV_FILE" ]]; then
        # shellcheck disable=SC1090
        mysql_root_pwd="$(grep '^MYSQL_ROOT_PASSWORD=' "$ENV_FILE" | cut -d= -f2-)"
        zbx_root_pwd="$(grep '^ZABBIX_DB_ROOT_PASSWORD=' "$ENV_FILE" | cut -d= -f2-)"
    fi

    # 1. SSH endurecido
    if command -v sshd >/dev/null 2>&1; then
        if sshd -T 2>/dev/null | grep -qE '^permitrootlogin no$'; then
            _record 1 "PermitRootLogin no" 0
        else
            _record 1 "PermitRootLogin no" 1 "sshd no reporta permitrootlogin=no"
        fi
    else
        _record 1 "PermitRootLogin no" 1 "sshd no instalado"
    fi

    # 2. Puerto SSH escucha
    if ss -ltn "sport = :${SB_SSH_PORT}" 2>/dev/null | grep -q ":${SB_SSH_PORT}\b"; then
        _record 2 "Puerto SSH ${SB_SSH_PORT} escuchando" 0
    else
        _record 2 "Puerto SSH ${SB_SSH_PORT} escuchando" 1 "no hay listener"
    fi

    # 3. UFW activo
    if command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | grep -q "Status: active"; then
        _record 3 "UFW activo" 0
    else
        _record 3 "UFW activo" 1 "ufw no activo"
    fi

    # 4. Reglas UFW contienen SSH_PORT + APP_PORT
    if command -v ufw >/dev/null 2>&1; then
        local ufw_ok=true
        ufw status 2>/dev/null | grep -q "${SB_SSH_PORT}/tcp.*ALLOW" || ufw_ok=false
        ufw status 2>/dev/null | grep -q "${SB_APP_PORT}/tcp.*ALLOW" || ufw_ok=false
        if $ufw_ok; then
            _record 4 "Reglas UFW (SSH+APP)" 0
        else
            _record 4 "Reglas UFW (SSH+APP)" 1 "faltan reglas para ${SB_SSH_PORT} o ${SB_APP_PORT}"
        fi
    else
        _record 4 "Reglas UFW (SSH+APP)" 1 "ufw no instalado"
    fi

    # 5. Docker activo
    if systemctl is-active docker >/dev/null 2>&1; then
        _record 5 "Docker daemon activo" 0
    else
        _record 5 "Docker daemon activo" 1 "docker no activo"
    fi

    # 6. songbird_app miembro de docker
    if id songbird_app 2>/dev/null | grep -q "docker"; then
        _record 6 "songbird_app en grupo docker" 0
    else
        _record 6 "songbird_app en grupo docker" 1 "songbird_app no es miembro de docker"
    fi

    # 7. docker compose plugin
    if docker compose version >/dev/null 2>&1; then
        _record 7 "docker compose plugin" 0
    else
        _record 7 "docker compose plugin" 1 "compose no instalado"
    fi

    # 8. Contenedores healthy
    if [[ -f "$COMPOSE_FILE" ]]; then
        local unhealthy
        unhealthy="$(docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" ps --format json 2>/dev/null | \
            jq '[.[] | select(.Health!="healthy" and .Health!="")] | length' 2>/dev/null || echo 99)"
        if [[ "$unhealthy" -eq 0 ]]; then
            _record 8 "Todos los contenedores healthy" 0
        else
            _record 8 "Todos los contenedores healthy" 1 "$unhealthy contenedores no-healthy"
        fi
    else
        _record 8 "Todos los contenedores healthy" 1 "compose file no existe en $SB_REPO_DIR"
    fi

    # 9. App responde
    local app_code
    app_code="$(curl -fsS -o /dev/null -w "%{http_code}" "http://localhost:${SB_APP_PORT}/" 2>/dev/null || echo 000)"
    if [[ "$app_code" =~ ^[2345][0-9][0-9]$ ]]; then
        _record 9 "App responde (HTTP $app_code)" 0
    else
        _record 9 "App responde" 1 "HTTP $app_code"
    fi

    # 10. Zabbix responde
    local zbx_code
    zbx_code="$(curl -fsS -o /dev/null -w "%{http_code}" "http://localhost:${SB_APP_PORT}/zabbix/" 2>/dev/null || echo 000)"
    if [[ "$zbx_code" =~ ^[2345][0-9][0-9]$ ]]; then
        _record 10 "Zabbix responde (HTTP $zbx_code)" 0
    else
        _record 10 "Zabbix responde" 1 "HTTP $zbx_code"
    fi

    # 11. BD app ping
    if [[ -n "${mysql_root_pwd:-}" ]] && docker inspect songbird_db >/dev/null 2>&1; then
        if docker exec -e MYSQL_ROOT_PASSWORD="$mysql_root_pwd" songbird_db \
            mariadb-admin ping --protocol=tcp -h 127.0.0.1 -uroot \
            -p"${mysql_root_pwd}" --silent >/dev/null 2>&1; then
            _record 11 "BD app (songbird_db) ping" 0
        else
            _record 11 "BD app (songbird_db) ping" 1 "mariadb-admin ping falló"
        fi
    else
        _record 11 "BD app (songbird_db) ping" 1 "contenedor no existe o password no leído"
    fi

    # 12. BD Zabbix ping
    if [[ -n "${zbx_root_pwd:-}" ]] && docker inspect zabbix-db-prod >/dev/null 2>&1; then
        if docker exec -e MYSQL_ROOT_PASSWORD="$zbx_root_pwd" zabbix-db-prod \
            mysqladmin ping -uroot -p"${zbx_root_pwd}" --silent >/dev/null 2>&1; then
            _record 12 "BD Zabbix (zabbix-db-prod) ping" 0
        else
            _record 12 "BD Zabbix (zabbix-db-prod) ping" 1 "mysqladmin ping falló"
        fi
    else
        _record 12 "BD Zabbix (zabbix-db-prod) ping" 1 "contenedor no existe o password no leído"
    fi

    # 13. Cron backup
    if [[ -f /etc/cron.d/songbird-backup ]]; then
        _record 13 "Cron backup configurado" 0
    else
        _record 13 "Cron backup configurado" 1 "/etc/cron.d/songbird-backup no existe"
    fi

    # 14. Logrotate sin errores
    if [[ -f /etc/logrotate.d/songbird ]]; then
        if logrotate -d /etc/logrotate.d/songbird >/dev/null 2>&1; then
            _record 14 "Logrotate válido" 0
        else
            _record 14 "Logrotate válido" 1 "logrotate -d reportó errores"
        fi
    else
        _record 14 "Logrotate válido" 1 "/etc/logrotate.d/songbird no existe"
    fi

    # 15. .env chmod 600
    if [[ -f "$ENV_FILE" ]]; then
        local mode
        mode="$(stat -c '%a' "$ENV_FILE" 2>/dev/null || echo 000)"
        if [[ "$mode" == "600" ]]; then
            _record 15 ".env permisos 600" 0
        else
            _record 15 ".env permisos 600" 1 "permisos actuales: $mode"
        fi
    else
        _record 15 ".env permisos 600" 1 "$ENV_FILE no existe"
    fi

    # Resumen
    printf '\n' >&2
    if [[ $SB_FAIL_COUNT -eq 0 ]]; then
        logger_ok "Smoke test: 15/15 OK"
        return 0
    fi
    logger_error "Smoke test: $SB_FAIL_COUNT fallos de 15"
    return "$SB_FAIL_COUNT"
}

mod_verificar_status() {
    mod_verificar_run
}
