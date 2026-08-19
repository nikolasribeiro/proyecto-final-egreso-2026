#!/usr/bin/env bash
# scripts/lib/deploy.sh
# Regenera .env, despliega el stack via docker-compose.prod.yml.
# NO clona el repo — asume /opt/songbird-app ya presente.
# Modulo: deploy

set -euo pipefail
IFS=$'\n\t'

ENV_FILE="${SB_REPO_DIR}/.env"
ENV_EXAMPLE="${SB_REPO_DIR}/.env.example"
COMPOSE_FILE="${SB_REPO_DIR}/docker-compose.prod.yml"

mod_deploy_run() {
    logger_section "Módulo deploy (NO clona)"

    if [[ ! -d "$SB_REPO_DIR" ]]; then
        logger_error "Repo no encontrado en $SB_REPO_DIR. Cloná el repo manualmente y re-ejecutá."
        return 64
    fi
    if [[ ! -d "$SB_REPO_DIR/.git" ]]; then
        logger_warn "$SB_REPO_DIR no parece ser un repo git (.git ausente)"
    fi
    if [[ ! -f "$COMPOSE_FILE" ]]; then
        logger_error "No se encontró $COMPOSE_FILE"
        return 64
    fi
    if [[ ! -f "$ENV_EXAMPLE" ]]; then
        logger_error "No se encontró $ENV_EXAMPLE (¿hiciste git pull?)"
        return 64
    fi

    # Idempotencia: si el stack está corriendo y .env existe, skip
    if [[ -f "$ENV_FILE" ]] && \
        docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" ps --services --filter status=running 2>/dev/null | \
        grep -q .; then
        local running
        running="$(docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" ps --services --filter status=running 2>/dev/null | wc -l)"
        if [[ "$running" -ge 9 ]]; then
            logger_ok "[deploy] ya hay $running servicios corriendo (skip)"
            state_set_module_completed deploy \
                '{"containers_running": '"${running}"', "skipped": true}'
            return 0
        fi
    fi

    # 1. Tomar control del directorio
    run_cmd install -d -m 755 -o root -g root "$SB_REPO_DIR"
    if id songbird_admin >/dev/null 2>&1; then
        run_cmd chown -R "${SB_ADMIN_USER}:${SB_ADMIN_USER}" "$SB_REPO_DIR" 2>/dev/null || true
    fi

    # 2. Copiar .env.example -> .env (si no existe)
    if [[ ! -f "$ENV_FILE" ]]; then
        run_cmd cp -n "$ENV_EXAMPLE" "$ENV_FILE"
        logger_ok "Creado $ENV_FILE desde .env.example"
    else
        logger_info "Preservando $ENV_FILE existente"
    fi

    # 3. Regenerar credenciales Zabbix SIEMPRE (defaults literales rompen healthchecks)
    local zbx_pwd zbx_root_pwd
    zbx_pwd="$(sb_gen_password)"
    zbx_root_pwd="$(sb_gen_password)"

    # Reemplazar líneas en .env (in-place, con sed delimitador |)
    if grep -qE "^ZABBIX_DB_PASSWORD=" "$ENV_FILE"; then
        run_cmd sed -i.bak "s|^ZABBIX_DB_PASSWORD=.*|ZABBIX_DB_PASSWORD=${zbx_pwd}|" "$ENV_FILE"
    else
        run_cmd bash -c "echo 'ZABBIX_DB_PASSWORD=${zbx_pwd}' >> '$ENV_FILE'"
    fi
    if grep -qE "^ZABBIX_DB_ROOT_PASSWORD=" "$ENV_FILE"; then
        run_cmd sed -i.bak "s|^ZABBIX_DB_ROOT_PASSWORD=.*|ZABBIX_DB_ROOT_PASSWORD=${zbx_root_pwd}|" "$ENV_FILE"
    else
        run_cmd bash -c "echo 'ZABBIX_DB_ROOT_PASSWORD=${zbx_root_pwd}' >> '$ENV_FILE'"
    fi

    # 4. Forzar APP_ENVIRONMENT=production
    if grep -qE "^APP_ENVIRONMENT=" "$ENV_FILE"; then
        run_cmd sed -i "s|^APP_ENVIRONMENT=.*|APP_ENVIRONMENT=production|" "$ENV_FILE"
    else
        run_cmd bash -c "echo 'APP_ENVIRONMENT=production' >> '$ENV_FILE'"
    fi

    # 5. Forzar APP_PORT
    if grep -qE "^APP_PORT=" "$ENV_FILE"; then
        run_cmd sed -i "s|^APP_PORT=.*|APP_PORT=${SB_APP_PORT}|" "$ENV_FILE"
    fi

    # 6. Permisos
    run_cmd chmod 600 "$ENV_FILE"
    if id songbird_admin >/dev/null 2>&1; then
        run_cmd chown "${SB_ADMIN_USER}:${SB_ADMIN_USER}" "$ENV_FILE" 2>/dev/null || true
    fi

    # 7. Detectar volumen mariadb_data preexistente
    if docker volume inspect mariadb_data >/dev/null 2>&1; then
        logger_warn "Volumen mariadb_data preexistente detectado. Si tiene schema viejo, usar --reset-volumes para recrearlo (DESTRUCTIVO)."
    fi

    # 8. Pull + up
    logger_info "docker compose pull..."
    run_cmd docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" pull

    logger_info "docker compose up -d..."
    run_cmd docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" up -d

    # 9. Esperar healthchecks (timeout 180s)
    logger_info "Esperando healthchecks (max 180s)..."
    local waited=0
    local healthy_count=0
    local total_services
    total_services="$(docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" ps --services 2>/dev/null | wc -l)"

    while [[ $waited -lt 180 ]]; do
        healthy_count="$(docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" ps --format json 2>/dev/null | \
            jq '[.[] | select(.Health=="healthy" or .Health=="") ] | length' 2>/dev/null || echo 0)"
        if [[ "$healthy_count" -ge "$total_services" ]]; then
            break
        fi
        sleep 5
        waited=$((waited + 5))
        printf '.' >&2
    done
    printf '\n' >&2

    # 10. Estado final
    local running
    running="$(docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" ps --services --filter status=running 2>/dev/null | wc -l)"

    sb_kv "Servicios corriendo" "${running}/${total_services}"
    sb_kv "Healthy" "${healthy_count}/${total_services}"
    sb_kv "APP URL" "http://localhost:${SB_APP_PORT}/"
    sb_kv "Zabbix URL" "http://localhost:${SB_APP_PORT}/zabbix/"

    # Guardar credenciales generadas (root only, chmod 600)
    if [[ "$SB_DRY_RUN" != "true" ]]; then
        run_cmd install -d -m 700 -o root -g root /opt/songbird-operator
        run_cmd tee "$SB_CRED_FILE" >/dev/null <<EOF
# Generado por songbird-operator (issue #35). NO compartir.
# App
MYSQL_DATABASE=\$(grep ^MYSQL_DATABASE= $ENV_FILE | cut -d= -f2-)
MYSQL_USER=\$(grep ^MYSQL_USER= $ENV_FILE | cut -d= -f2-)
MYSQL_PASSWORD=\$(grep ^MYSQL_PASSWORD= $ENV_FILE | cut -d= -f2-)
MYSQL_ROOT_PASSWORD=\$(grep ^MYSQL_ROOT_PASSWORD= $ENV_FILE | cut -d= -f2-)
# Zabbix
ZABBIX_DB_PASSWORD=${zbx_pwd}
ZABBIX_DB_ROOT_PASSWORD=${zbx_root_pwd}
EOF
        run_cmd chmod 600 "$SB_CRED_FILE"
        run_cmd chown root:root "$SB_CRED_FILE"
    fi

    if [[ $running -lt 9 ]]; then
        logger_warn "Solo $running servicios corriendo (esperado >= 9). Revisá: docker compose -f $COMPOSE_FILE ps"
    fi

    state_set_module_completed deploy \
        '{"containers_running": '"${running}"', "app_url": "http://localhost:'"${SB_APP_PORT}"'/", "zabbix_url": "http://localhost:'"${SB_APP_PORT}"'/zabbix/"}'

    logger_ok "Stack desplegado"
}

mod_deploy_status() {
    if [[ -d "$SB_REPO_DIR" ]]; then
        cd "$SB_REPO_DIR"
        if [[ -f docker-compose.prod.yml ]]; then
            docker compose -f docker-compose.prod.yml ps 2>/dev/null
        else
            echo "docker-compose.prod.yml no encontrado en $SB_REPO_DIR"
        fi
    else
        echo "$SB_REPO_DIR no existe"
    fi
}
