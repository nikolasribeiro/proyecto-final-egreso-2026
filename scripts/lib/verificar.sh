#!/usr/bin/env bash
# scripts/lib/verificar.sh
# Smoke test del host + el stack (21 checks).
# Modulo: verificar
#
# Checks 1-15  : estado del host y del stack (los originales)
# Checks 16-21 : estrategia master-slave (replicación + split read/write)
#
# Los nombres de contenedor NO están hardcodeados: se resuelven desde el
# compose file (ver sb_compose_container_of_service), asi que el smoke test
# sobrevive a los renombres de `container_name`.

set -euo pipefail
IFS=$'\n\t'

COMPOSE_FILE="${SB_REPO_DIR}/${SB_COMPOSE_FILE_NAME}"
ENV_FILE="${SB_REPO_DIR}/.env"

# Resuelve las rutas del proyecto en el momento de usarlas (ver nota en
# scripts/lib/deploy.sh).
sb_verificar_paths() {
    COMPOSE_FILE="${SB_REPO_DIR}/${SB_COMPOSE_FILE_NAME}"
    ENV_FILE="${SB_REPO_DIR}/.env"
}

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

# Ejecuta SQL dentro de un contenedor de base de datos y devuelve el valor.
_db_query() {
    local container="$1" root_pwd="$2" sql="$3"
    docker exec -e MYSQL_ROOT_PASSWORD="$root_pwd" "$container" \
        mariadb -uroot -p"${root_pwd}" --protocol=tcp -h 127.0.0.1 -N -B -e "$sql" 2>/dev/null
}

# Igual que _db_query pero CONSERVANDO la fila de encabezados.
# Necesario para parsear `SHOW SLAVE STATUS`, que devuelve ~60 columnas.
_db_query_with_header() {
    local container="$1" root_pwd="$2" sql="$3"
    docker exec -e MYSQL_ROOT_PASSWORD="$root_pwd" "$container" \
        mariadb -uroot -p"${root_pwd}" --protocol=tcp -h 127.0.0.1 -B -e "$sql" 2>/dev/null
}

mod_verificar_run() {
    logger_section "Smoke test (21 checks)"

    sb_verificar_paths

    SB_FAIL_COUNT=0

    # --- Credenciales desde el .env -------------------------------------
    local mysql_root_pwd zbx_root_pwd
    if [[ -f "$ENV_FILE" ]]; then
        # shellcheck disable=SC1090
        mysql_root_pwd="$(grep '^MYSQL_ROOT_PASSWORD=' "$ENV_FILE" | tail -n1 | cut -d= -f2-)"
        # shellcheck disable=SC1090
        zbx_root_pwd="$(grep '^ZABBIX_DB_ROOT_PASSWORD=' "$ENV_FILE" | tail -n1 | cut -d= -f2-)"
    fi

    # --- Contenedores resueltos desde el compose file ------------------
    local c_master c_slave c_zbx_db
    c_master="$(sb_compose_container_of_service "$COMPOSE_FILE" "$ENV_FILE" db-master)"
    c_slave="$(sb_compose_container_of_service "$COMPOSE_FILE" "$ENV_FILE" db-slave)"
    c_zbx_db="$(sb_compose_container_of_service "$COMPOSE_FILE" "$ENV_FILE" zabbix-db)"

    # ================================================================
    # 1-15 : HOST + STACK
    # ================================================================

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
        if [[ "$ufw_ok" == "true" ]]; then
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

    # 8. Contenedores healthy (los one-shot(count exited 0) tambien valen)
    if [[ -f "$COMPOSE_FILE" ]]; then
        local pending
        pending="$(sb_compose_pending_services "$COMPOSE_FILE" "$ENV_FILE" || true)"
        if [[ -z "$pending" ]]; then
            _record 8 "Todos los contenedores healthy" 0
        else
            _record 8 "Todos los contenedores healthy" 1 "pendientes: $(printf '%s' "$pending" | tr '\n' ' ')"
        fi
    else
        _record 8 "Todos los contenedores healthy" 1 "compose file no existe en $SB_REPO_DIR"
    fi

    # 9. App responde
    local app_code
    app_code="$(curl -fsS -o /dev/null -w "%{http_code}" "http://localhost:${SB_APP_PORT}/" 2>/dev/null || true)"
    app_code="${app_code:-000}"
    if [[ "$app_code" =~ ^[234][0-9][0-9]$ ]]; then
        _record 9 "App responde (HTTP $app_code)" 0
    else
        _record 9 "App responde" 1 "HTTP $app_code"
    fi

    # 10. Zabbix responde
    local zbx_code
    zbx_code="$(curl -fsS -o /dev/null -w "%{http_code}" "http://localhost:${SB_APP_PORT}/zabbix/" 2>/dev/null || true)"
    zbx_code="${zbx_code:-000}"
    if [[ "$zbx_code" =~ ^[234][0-9][0-9]$ ]]; then
        _record 10 "Zabbix responde (HTTP $zbx_code)" 0
    else
        _record 10 "Zabbix responde" 1 "HTTP $zbx_code"
    fi

    # 11. BD app (master) ping
    if [[ -n "${mysql_root_pwd:-}" && -n "$c_master" ]] && docker inspect "$c_master" >/dev/null 2>&1; then
        if docker exec -e MYSQL_ROOT_PASSWORD="$mysql_root_pwd" "$c_master" \
            mariadb-admin ping --protocol=tcp -h 127.0.0.1 -uroot \
            -p"${mysql_root_pwd}" --silent >/dev/null 2>&1; then
            _record 11 "BD master (${c_master}) ping" 0
        else
            _record 11 "BD master (${c_master}) ping" 1 "mariadb-admin ping falló"
        fi
    else
        _record 11 "BD master ping" 1 "contenedor no existe o password no leído"
    fi

    # 12. BD Zabbix ping
    if [[ -n "${zbx_root_pwd:-}" && -n "$c_zbx_db" ]] && docker inspect "$c_zbx_db" >/dev/null 2>&1; then
        if docker exec -e MYSQL_ROOT_PASSWORD="$zbx_root_pwd" "$c_zbx_db" \
            mysqladmin ping -uroot -p"${zbx_root_pwd}" --silent >/dev/null 2>&1; then
            _record 12 "BD Zabbix (${c_zbx_db}) ping" 0
        else
            _record 12 "BD Zabbix (${c_zbx_db}) ping" 1 "mysqladmin ping falló"
        fi
    else
        _record 12 "BD Zabbix ping" 1 "contenedor no existe o password no leído"
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

    # ================================================================
    # 16-21 : ESTRATEGIA MASTER-SLAVE
    # ================================================================

    # 16. Master: binlog habilitado + server_id propio
    if [[ -n "${mysql_root_pwd:-}" && -n "$c_master" ]] && docker inspect "$c_master" >/dev/null 2>&1; then
        local m_sid m_bin
        m_sid="$(_db_query "$c_master" "$mysql_root_pwd" "SELECT @@server_id;" || true)"
        m_bin="$(_db_query "$c_master" "$mysql_root_pwd" "SELECT @@log_bin;" || true)"
        if [[ "$m_sid" == "1" && "$m_bin" == "1" ]]; then
            _record 16 "Master: log_bin=1 y server_id=1" 0
        else
            _record 16 "Master: log_bin=1 y server_id=1" 1 "server_id=${m_sid:-?} log_bin=${m_bin:-?}"
        fi
    else
        _record 16 "Master: log_bin=1 y server_id=1" 1 "contenedor master no disponible"
    fi

    # 17. Slave: solo lectura + server_id propio
    if [[ -n "${mysql_root_pwd:-}" && -n "$c_slave" ]] && docker inspect "$c_slave" >/dev/null 2>&1; then
        local s_sid s_ro
        s_sid="$(_db_query "$c_slave" "$mysql_root_pwd" "SELECT @@server_id;" || true)"
        s_ro="$(_db_query "$c_slave" "$mysql_root_pwd" "SELECT @@read_only;" || true)"
        if [[ "$s_sid" == "2" && "$s_ro" == "1" ]]; then
            _record 17 "Slave: read_only=1 y server_id=2" 0
        else
            _record 17 "Slave: read_only=1 y server_id=2" 1 "server_id=${s_sid:-?} read_only=${s_ro:-?}"
        fi
    else
        _record 17 "Slave: read_only=1 y server_id=2" 1 "contenedor slave no disponible"
    fi

    # 18. Replicacion activa (threads IO y SQL corriendo)
    if [[ -n "${mysql_root_pwd:-}" && -n "$c_slave" ]] && docker inspect "$c_slave" >/dev/null 2>&1; then
        local slave_running
        slave_running="$(_db_query "$c_slave" "$mysql_root_pwd" "SHOW GLOBAL STATUS LIKE 'Slave_running';" | cut -f2 || true)"
        if [[ "$slave_running" == "ON" ]]; then
            _record 18 "Replicación activa (IO+SQL threads)" 0
        else
            _record 18 "Replicación activa (IO+SQL threads)" 1 "Slave_running=${slave_running:-OFF/NULL}"
        fi
    else
        _record 18 "Replicación activa (IO+SQL threads)" 1 "contenedor slave no disponible"
    fi

    # 19. Sin errores de replicacion
    if [[ -n "${mysql_root_pwd:-}" && -n "$c_slave" ]] && docker inspect "$c_slave" >/dev/null 2>&1; then
        # `SHOW SLAVE STATUS` devuelve ~60 columnas: se parsea por nombre.
        local repl_errors
        repl_errors="$(_db_query_with_header "$c_slave" "$mysql_root_pwd" "SHOW SLAVE STATUS;" \
            | awk -F'\t' '
                NR==1 { for (i = 1; i <= NF; i++) h[$i] = i; next }
                {
                    io = (("Last_IO_Error" in h) ? $h["Last_IO_Error"] : "")
                    sql = (("Last_SQL_Error" in h) ? $h["Last_SQL_Error"] : "")
                    if (io != "" || sql != "") {
                        printf "IO=[%s] SQL=[%s]", io, sql
                    }
                }
            ' || true)"
        if [[ -z "$repl_errors" ]]; then
            _record 19 "Replicación sin errores" 0
        else
            _record 19 "Replicación sin errores" 1 "$repl_errors"
        fi
    else
        _record 19 "Replicación sin errores" 1 "contenedor slave no disponible"
    fi

    # 20. GTID del slave al menos igual al del master (sin lag pendiente)
    if [[ -n "${mysql_root_pwd:-}" && -n "$c_master" && -n "$c_slave" ]]; then
        local g_master g_slave
        g_master="$(_db_query "$c_master" "$mysql_root_pwd" "SELECT @@gtid_binlog_pos;" || true)"
        g_slave="$(_db_query "$c_slave" "$mysql_root_pwd" "SELECT @@gtid_binlog_pos;" || true)"
        # El GTID del master es "dominio-servidor-seq". Solo comparamos el seq
        # del servidor master (server_id=1): "0-1-42".
        # `|| true` es obligatorio: sin coincidencias, `grep` devuelve 1 y con
        # `set -e` + `pipefail` la asignacion abortaria el smoke test.
        local master_seq slave_seq
        master_seq="$(printf '%s' "$g_master" | tr ',' '\n' | grep -E '(^|,)0-1-[0-9]+$' | tail -n1 | sed -E 's/.*0-1-([0-9]+).*/\1/' || true)"
        slave_seq="$(printf '%s' "$g_slave" | tr ',' '\n' | grep -E '(^|,)0-1-[0-9]+$' | tail -n1 | sed -E 's/.*0-1-([0-9]+).*/\1/' || true)"
        if [[ -n "$master_seq" && -n "$slave_seq" && "$slave_seq" -ge "$master_seq" ]]; then
            _record 20 "Slave al día (GTID $slave_seq >= $master_seq)" 0
        else
            _record 20 "Slave al día (GTID)" 1 "master=${master_seq:-?} slave=${slave_seq:-?}"
        fi
    else
        _record 20 "Slave al día (GTID)" 1 "contenedores no disponibles"
    fi

    # 21. Split read/write configurado en la app
    local read_replica wait_after_write db_slave_host
    read_replica="$(sb_env_get "$ENV_FILE" DB_READ_REPLICA_ENABLED 2>/dev/null || true)"
    wait_after_write="$(sb_env_get "$ENV_FILE" DB_REPLICA_WAIT_AFTER_WRITE 2>/dev/null || true)"
    db_slave_host="$(sb_env_get "$ENV_FILE" DB_SLAVE_HOST 2>/dev/null || true)"
    # El split debe estar activo. La espera post-escritura es opcional: se
    # reporta como informacion, no como fallo.
    if [[ -n "$db_slave_host" && "$read_replica" != "false" && "$read_replica" != "0" ]]; then
        _record 21 "Split read/write activo (lecturas -> ${db_slave_host})" 0
        if [[ "$wait_after_write" == "true" ]]; then
            logger_info "[verificar] espera post-escritura activa (read-your-writes garantizado)"
        else
            logger_warn "[verificar] DB_REPLICA_WAIT_AFTER_WRITE no activo: una lectura posterior a una escritura podría no ver el dato"
        fi
    else
        _record 21 "Split read/write activo" 1 "DB_READ_REPLICA_ENABLED=${read_replica:-unset} DB_SLAVE_HOST=${db_slave_host:-unset}"
    fi

    # --- Resumen -------------------------------------------------------
    printf '\n' >&2
    if [[ $SB_FAIL_COUNT -eq 0 ]]; then
        logger_ok "Smoke test: 21/21 OK"
        return 0
    fi
    logger_error "Smoke test: $SB_FAIL_COUNT fallos de 21"
    return "$SB_FAIL_COUNT"
}

mod_verificar_status() {
    mod_verificar_run
}
