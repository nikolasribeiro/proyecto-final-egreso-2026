#!/usr/bin/env bash
# scripts/lib/repo.sh
# Verifica (y opcionalmente sincroniza) el proyecto del despliegue.
# Modulo: repo
#
# FLUJO SOPORTADO (el del operador)
# ----------------------------------
#   1. instalo Debian + git
#   2. git clone ... ~/proyecto        <- el operador YA está clonado
#   3. edito el .env, cargo mis variables
#   4. sudo scripts/operator.sh --all  <- el operador USA eso
#
# Por lo tanto este modulo:
#   - NO clona nada (el proyecto ya esta clonado por el operador)
#   - NO copia nada por defecto
#   - opera IN-PLACE sobre el repo donde vive el script
#
# Que hace entonces?
#   1. Detecta el checkout por la UBICACION DE LOS SCRIPTS (scripts/lib/../..),
#      no por el CWD.
#   2. Verifica que esten `docker-compose.yml` y `.env`.
#      Si falta el `.env`, lo crea desde `.env.example`.
#   3. Si se paso `--repo-dir <otro path>` explicito, sincroniza el checkout
#      hacia ese destino INCLUYENDO tu `.env` (para no perder tus variables).

set -euo pipefail
IFS=$'\n\t'

mod_repo_run() {
    logger_section "Módulo repo (verificación del proyecto)"

    local src dst
    src="$(sb_realpath "${SB_SOURCE_DIR}")"
    dst="${SB_REPO_DIR}"

    sb_kv "Checkout" "$src"
    sb_kv "Directorio de despliegue" "$dst"

    # --- 1. ¿Es el checkout un proyecto? --------------------------------
    if [[ ! -f "${src}/${SB_COMPOSE_FILE_NAME}" ]]; then
        logger_error "No se encontró ${SB_COMPOSE_FILE_NAME} en $src"
        logger_error "El operador no clona el repo: cloná el proyecto y volvé a ejecutarlo desde el"
        logger_error "directorio del checkout (por ejemplo: git clone <url> ~/proyecto && cd ~/proyecto)."
        return 64
    fi
    if [[ ! -f "${src}/.env.example" ]]; then
        logger_error "No se encontró .env.example en $src"
        return 64
    fi
    logger_ok "Proyecto detectado (compose file y .env.example presentes)"

    # --- 2. Caso in-place: el destino es el propio checkout --------------
    if [[ "$src" == "$(sb_realpath "$dst")" ]]; then
        logger_ok "Operación in-place en $dst (sin sincronización)"

        # El .env es configuración del operador: si no existe, se crea desde el
        # ejemplo para que el deploy pueda completarlo. Si existe, NO se toca.
        if [[ ! -f "${dst}/.env" ]]; then
            logger_warn "No existe ${dst}/.env. Se crea desde .env.example (completá tus variables)."
            run_cmd cp -n "${dst}/.env.example" "${dst}/.env"
            run_cmd chmod 600 "${dst}/.env"
        else
            logger_info "Se conserva el .env existente (tu configuración manda)"
        fi

        state_set_module_completed repo \
            '{"mode": "in-place", "path": "'"${dst}"'"}'
        return 0
    fi

    # --- 3. Destino explicito (--repo-dir): sincronizar -----------------
    # Solo llega aca si el operador pidió desplegar en otro directorio.
    # La sincronización INCLUYE el `.env` para no perder tus variables.
    if [[ "$SB_REPO_DIR_EXPLICIT" != "true" ]]; then
        logger_error "El directorio de despliegue ($dst) no coincide con el checkout ($src)"
        logger_error "Pasá --repo-dir explícitamente si querés desplegar en otro lado."
        return 64
    fi

    logger_info "Sincronizando el checkout hacia $dst (incluye tu .env)"
    sb_ensure_dir "$dst" 755 root root
    run_cmd rsync -a "${src}/" "${dst}/"

    if [[ ! -f "${dst}/${SB_COMPOSE_FILE_NAME}" ]]; then
        logger_error "La sincronización no dejó ${SB_COMPOSE_FILE_NAME} en $dst"
        return 70
    fi

    if [[ ! -f "${dst}/.env" ]]; then
        logger_warn "No existe ${dst}/.env. Se crea desde .env.example."
        run_cmd cp -n "${dst}/.env.example" "${dst}/.env"
        run_cmd chmod 600 "${dst}/.env"
    else
        logger_ok "Tu .env fue sincronizado (variables preservadas)"
    fi

    logger_ok "Proyecto sincronizado en $dst"
    state_set_module_completed repo \
        '{"mode": "sync", "source": "'"${src}"'", "path": "'"${dst}"'"}'
}

mod_repo_status() {
    local dst="${SB_REPO_DIR}"
    if [[ -f "${dst}/${SB_COMPOSE_FILE_NAME}" ]]; then
        echo "Proyecto de despliegue: $dst"
        echo "Compose file: ${dst}/${SB_COMPOSE_FILE_NAME}"
        echo ".env: $([[ -f "${dst}/.env" ]] && echo presente || echo ausente)"
    else
        echo "$dst NO contiene el proyecto"
    fi
}
