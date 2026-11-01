#!/usr/bin/env bash
# scripts/lib/deploy.sh
# Genera el .env (solo los huecos) y despliega el stack con docker compose.
# Modulo: deploy
#
# RESPETO POR LA CONFIGURACION DEL USUARIO
# ----------------------------------------
# El `.env` es configuracion del operador. Este modulo:
#   - NO sobreescribe valores que ya definiste (PMA_HOST, DB_HOST, APP_PORT,
#     APP_ENVIRONMENT, tus credenciales...).
#   - Solo RELLENA lo que esta vacio (nombres de BD, usuario, secretos).
#   - Solo FUERZA APP_PORT / APP_ENVIRONMENT si los pediste por flag
#     (`--app-port`, `--environment`).
#
# CAMBIOS IMPORTANTES (v0.2.0)
# ----------------------------
# 1. Usa el compose file UNICO `docker-compose.yml` (dev y prod).
#    Antes apuntaba a `docker-compose.prod.yml`, que fue eliminado del repo:
#    el deploy fallaba SIEMPRE con "No se encontró ..." y abortaba el `--all`.
# 2. Genera las credenciales que quedaron vacias en `.env.example`
#    (MYSQL_DATABASE/USER/PASSWORD/ROOT_PASSWORD). Antes solo se regeneraban
#    las de Zabbix y la BD de la app quedaba sin credenciales.
# 3. Las credenciales de Zabbix SOLO se generan si estan vacias o son el
#    placeholder `cambiar_en_produccion`. Regenerarlas siempre rompia el stack
#    ya desplegado (la BD interna de Zabbix conservaba el password viejo).
# 4. La espera de healthchecks distingue contenedores one-shot (exited 0) de
#    los permanentes. Antes comparaba `healthy >= total_services`, lo que
#    NUNCA se cumplia y hacia esperar los 180s completos siempre.
# 5. `docker compose ps --format json` cambio a array JSON en Compose v2.21+.
#    El parser nuevo tolera ambos formatos.

set -euo pipefail
IFS=$'\n\t'

ENV_FILE="${SB_REPO_DIR}/.env"
ENV_EXAMPLE="${SB_REPO_DIR}/.env.example"

# Resuelve las rutas del proyecto en el momento de usarlas.
# (Los modulos se cargan dinamicamente, pero depender del valor de SB_REPO_DIR
#  al momento del `source` es fragil: si el flag se parsea despues, la ruta
#  quedaria desactualizada.)
sb_deploy_paths() {
    ENV_FILE="${SB_REPO_DIR}/.env"
    ENV_EXAMPLE="${SB_REPO_DIR}/.env.example"
}

# Valores por defecto de la base de la app (usados solo si estan vacios).
readonly SB_DEFAULT_DB_NAME="clinicas_db"
readonly SB_DEFAULT_DB_USER="songbird_app"
readonly SB_DEFAULT_REPL_USER="repl"

# Genera un secreto solo si el valor actual esta vacio o es un placeholder.
# Devuelve 0 si genero el valor, 1 si ya existia.
sb_env_ensure_secret() {
    local file="$1" key="$2" current
    current="$(sb_env_get "$file" "$key" 2>/dev/null || true)"
    if [[ -z "$current" || "$current" == "cambiar_en_produccion" || "$current" == "CHANGE_ME" || "$current" == "change_me" ]]; then
        sb_env_set "$file" "$key" "$(sb_gen_password)"
        return 0
    fi
    return 1
}

# Asegura que la clave exista con un valor no vacio. Si el valor es vacio se
# usa el default passed como $3.
sb_env_ensure_value() {
    local file="$1" key="$2" fallback="$3" current
    current="$(sb_env_get "$file" "$key" 2>/dev/null || true)"
    if [[ -z "$current" ]]; then
        sb_env_set "$file" "$key" "$fallback"
    fi
}

mod_deploy_run() {
    logger_section "Módulo deploy"

    sb_deploy_paths

    local compose_file
    compose_file="$(sb_compose_file)"

    # --- 0. Precondiciones ---------------------------------------------
    if [[ ! -d "$SB_REPO_DIR" ]]; then
        logger_error "Repo no encontrado en $SB_REPO_DIR."
        logger_error "Ejecutá primero: sudo scripts/operator.sh --module repo"
        return 64
    fi
    if [[ ! -f "$compose_file" ]]; then
        logger_error "No se encontró ${compose_file}"
        logger_error "El modulo 'repo' debe sincronizar el proyecto antes del deploy."
        return 64
    fi
    if [[ ! -f "$ENV_EXAMPLE" ]]; then
        logger_error "No se encontró $ENV_EXAMPLE (¿hiciste git pull?)"
        return 64
    fi
    if ! command -v docker >/dev/null 2>&1; then
        logger_error "docker no está instalado (¿ejecutaste el módulo 'docker'?)"
        return 70
    fi
    if ! docker compose version >/dev/null 2>&1; then
        logger_error "el plugin docker compose no está disponible"
        return 70
    fi

    # --- 1. Control del directorio -------------------------------------
    sb_ensure_dir "$SB_REPO_DIR" 755 root root

    # --- 2. .env base ---------------------------------------------------
    if [[ ! -f "$ENV_FILE" ]]; then
        run_cmd cp -n "$ENV_EXAMPLE" "$ENV_FILE"
        logger_ok "Creado $ENV_FILE desde .env.example"
    else
        logger_info "Preservando $ENV_FILE existente"
    fi

    # --- 3. Credenciales: solo se generan si faltan --------------------
    local generated=0

    if sb_env_ensure_secret "$ENV_FILE" MYSQL_PASSWORD; then
        logger_ok "MYSQL_PASSWORD generado"
        generated=$((generated + 1))
    fi
    if sb_env_ensure_secret "$ENV_FILE" MYSQL_ROOT_PASSWORD; then
        logger_ok "MYSQL_ROOT_PASSWORD generado"
        generated=$((generated + 1))
    fi
    if sb_env_ensure_secret "$ENV_FILE" MYSQL_REPLICATION_PASSWORD; then
        logger_ok "MYSQL_REPLICATION_PASSWORD generado"
        generated=$((generated + 1))
    fi
    if sb_env_ensure_secret "$ENV_FILE" ZABBIX_DB_PASSWORD; then
        logger_ok "ZABBIX_DB_PASSWORD generado"
        generated=$((generated + 1))
    fi
    if sb_env_ensure_secret "$ENV_FILE" ZABBIX_DB_ROOT_PASSWORD; then
        logger_ok "ZABBIX_DB_ROOT_PASSWORD generado"
        generated=$((generated + 1))
    fi

    # --- 4. Valores no secretos: se completan SOLO si faltan -------------
    # El `.env` es configuración del operador: lo que el usuario cargó a mano
    # se respeta. Acá únicamente se rellenan los huecos.
    sb_env_ensure_value "$ENV_FILE" MYSQL_DATABASE        "$SB_DEFAULT_DB_NAME"
    sb_env_ensure_value "$ENV_FILE" MYSQL_USER            "$SB_DEFAULT_DB_USER"
    sb_env_ensure_value "$ENV_FILE" MYSQL_REPLICATION_USER "$SB_DEFAULT_REPL_USER"
    sb_env_ensure_value "$ENV_FILE" PMA_USER              "$(sb_env_get "$ENV_FILE" MYSQL_USER || echo "$SB_DEFAULT_DB_USER")"
    sb_env_ensure_value "$ENV_FILE" PMA_PASSWORD          "$(sb_env_get "$ENV_FILE" MYSQL_PASSWORD || echo '')"

    # Hosts del split read/write. Si el usuario ya los definió, se respetan.
    sb_env_ensure_value "$ENV_FILE" PMA_HOST      "db-master"
    sb_env_ensure_value "$ENV_FILE" DB_HOST       "db-master"
    sb_env_ensure_value "$ENV_FILE" DB_SLAVE_HOST "db-slave"

    # Estrategia de split read/write.
    sb_env_ensure_value "$ENV_FILE" DB_READ_REPLICA_ENABLED     "true"
    sb_env_ensure_value "$ENV_FILE" DB_REPLICA_WAIT_AFTER_WRITE "true"
    sb_env_ensure_value "$ENV_FILE" DB_REPLICA_WAIT_TIMEOUT_MS  "2000"

    # --- 5. Valores forzados: SOLO si se pidieron por flag ---------------
    # Un `--app-port` que no diste no debe pisar el APP_PORT que ya
    # configuraste a mano.
    if [[ "$SB_ENVIRONMENT_EXPLICIT" == "true" ]]; then
        sb_env_set "$ENV_FILE" APP_ENVIRONMENT "$SB_ENVIRONMENT"
        logger_info "APP_ENVIRONMENT forzado a '$SB_ENVIRONMENT' (--environment)"
    fi
    if [[ "$SB_APP_PORT_EXPLICIT" == "true" ]]; then
        sb_env_set "$ENV_FILE" APP_PORT "${SB_APP_PORT}"
        logger_info "APP_PORT forzado a ${SB_APP_PORT} (--app-port)"
    fi

    # --- 6. Permisos del .env ------------------------------------------
    run_cmd chmod 600 "$ENV_FILE"
    if id "$SB_ADMIN_USER" >/dev/null 2>&1; then
        run_cmd chown "${SB_ADMIN_USER}:${SB_ADMIN_USER}" "$ENV_FILE" 2>/dev/null || true
    fi

    sb_kv ".env" "$ENV_FILE (600)"
    sb_kv "Secretos generados" "$generated"

    # --- 7. Volumenes de datos preexistentes ---------------------------
    local vol
    for vol in mariadb_master_data mariadb_slave_data; do
        if docker volume inspect "$vol" >/dev/null 2>&1; then
            logger_warn "Volumen $vol preexistente. Si tiene schema viejo, usar --reset-volumes para recrearlo (DESTRUCTIVO)."
        fi
    done

    # --- 8. ¿El stack ya está sano? (idempotencia) ---------------------
    local pending
    pending="$(sb_compose_pending_services "$compose_file" "$ENV_FILE" || true)"
    if [[ -z "$pending" ]]; then
        logger_ok "[deploy] el stack ya está completo y healthy (skip)"
        state_set_module_completed deploy '{"skipped": true}'
        mod_deploy_status_summary
        return 0
    fi
    logger_info "Servicios pendientes: $(printf '%s' "$pending" | tr '\n' ' ')"

    # --- 9. Build + pull + up ------------------------------------------
    logger_info "docker compose build..."
    run_cmd docker compose -f "$compose_file" --env-file "$ENV_FILE" build

    logger_info "docker compose pull..."
    # `--ignore-buildable` (Compose >= 2.20) evita intentar bajar imagenes
    # de servicios que se construyen localmente.
    if docker compose pull --help 2>&1 | grep -q -- "--ignore-buildable"; then
        run_cmd docker compose -f "$compose_file" --env-file "$ENV_FILE" pull --ignore-buildable
    else
        run_cmd docker compose -f "$compose_file" --env-file "$ENV_FILE" pull
    fi

    logger_info "docker compose up -d..."
    # `--remove-orphans` converge el host: elimina contenedores de servicios
    # que ya no existen en el compose file (p.ej. el viejo `songbird_db`).
    run_cmd docker compose -f "$compose_file" --env-file "$ENV_FILE" up -d --remove-orphans

    # --- 10. Esperar healthchecks --------------------------------------
    logger_info "Esperando healthchecks (max 300s)..."
    local wait_ok=true
    sb_compose_wait_healthy "$compose_file" "$ENV_FILE" 300 600 || wait_ok=false

    # --- 11. Estado final ----------------------------------------------
    local total pending_now
    total="$(docker compose -f "$compose_file" --env-file "$ENV_FILE" config --services 2>/dev/null | wc -l)"
    pending_now="$(sb_compose_pending_services "$compose_file" "$ENV_FILE" || true)"

    sb_kv "Servicios declarados" "$total"
    sb_kv "Servicios OK" "$(if [[ -z "$pending_now" ]]; then echo "$total/$total"; else echo "$((total - $(printf '%s\n' "$pending_now" | wc -l)))/$total"; fi)"
    sb_kv "APP URL" "http://localhost:${SB_APP_PORT}/"
    sb_kv "Zabbix URL" "http://localhost:${SB_APP_PORT}/zabbix/"

    # --- 12. Credenciales generadas (solo root) ------------------------
    if [[ "$SB_DRY_RUN" != "true" ]]; then
        sb_ensure_dir /opt/songbird-operator 700 root root
        run_cmd tee "$SB_CRED_FILE" >/dev/null <<EOF
# Generado por songbird-operator. NO compartir.
# App
MYSQL_DATABASE=$(sb_env_get "$ENV_FILE" MYSQL_DATABASE || echo '')
MYSQL_USER=$(sb_env_get "$ENV_FILE" MYSQL_USER || echo '')
MYSQL_PASSWORD=$(sb_env_get "$ENV_FILE" MYSQL_PASSWORD || echo '')
MYSQL_ROOT_PASSWORD=$(sb_env_get "$ENV_FILE" MYSQL_ROOT_PASSWORD || echo '')
# Replicacion master-slave
MYSQL_REPLICATION_USER=$(sb_env_get "$ENV_FILE" MYSQL_REPLICATION_USER || echo '')
MYSQL_REPLICATION_PASSWORD=$(sb_env_get "$ENV_FILE" MYSQL_REPLICATION_PASSWORD || echo '')
# Zabbix
ZABBIX_DB_PASSWORD=$(sb_env_get "$ENV_FILE" ZABBIX_DB_PASSWORD || echo '')
ZABBIX_DB_ROOT_PASSWORD=$(sb_env_get "$ENV_FILE" ZABBIX_DB_ROOT_PASSWORD || echo '')
EOF
        run_cmd chmod 600 "$SB_CRED_FILE"
        run_cmd chown root:root "$SB_CRED_FILE"
    fi

    if [[ -n "$pending_now" ]]; then
        logger_warn "Servicios no_OK: $(printf '%s' "$pending_now" | tr '\n' ' ')"
        logger_warn "Revisá: docker compose -f $compose_file ps"
    fi

    state_set_module_completed deploy \
        '{"app_url": "http://localhost:'"${SB_APP_PORT}"'/", "zabbix_url": "http://localhost:'"${SB_APP_PORT}"'/zabbix/"}'

    if [[ "$wait_ok" != "true" ]]; then
        return 1
    fi
    logger_ok "Stack desplegado"
}

mod_deploy_status_summary() {
    local compose_file
    compose_file="$(sb_compose_file)"
    [[ -f "$compose_file" ]] || return 0
    docker compose -f "$compose_file" --env-file "$ENV_FILE" ps 2>/dev/null | head -n 30
}

mod_deploy_status() {
    sb_deploy_paths
    if [[ -d "$SB_REPO_DIR" ]]; then
        cd "$SB_REPO_DIR" || return 1
        if [[ -f "$SB_COMPOSE_FILE_NAME" ]]; then
            docker compose -f "$SB_COMPOSE_FILE_NAME" ps 2>/dev/null
        else
            echo "$SB_COMPOSE_FILE_NAME no encontrado en $SB_REPO_DIR"
        fi
    else
        echo "$SB_REPO_DIR no existe"
    fi
}
