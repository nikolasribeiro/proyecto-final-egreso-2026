#!/usr/bin/env bash
# scripts/lib/preflight.sh
# Verificación del entorno: OS, root, DNS, puertos libres, docker.sock.
# Modulo: preflight

set -euo pipefail
IFS=$'\n\t'

mod_preflight_run() {
    logger_section "Módulo preflight"

    if module_skip_if_done preflight \
        '[[ "$(. /etc/os-release && echo "$ID")" == "debian" ]] && [[ "$(. /etc/os-release && echo "$VERSION_ID")" == "13" || "$(. /etc/os-release && echo "$VERSION_ID")" == "12" ]] && [[ "$(id -u)" == "0" ]]'; then
        return 0
    fi

    local failures=0

    # 1. OS
    if [[ -f /etc/os-release ]]; then
        # shellcheck disable=SC1091
        . /etc/os-release
        sb_kv "OS" "${PRETTY_NAME:-$ID $VERSION_ID}"
        if [[ "$ID" != "debian" ]]; then
            logger_error "OS no soportado: $ID (esperado debian)"
            failures=$((failures + 1))
        fi
        # 13 (trixie) es el objetivo; 12 (bookworm) sigue soportado.
        case "$VERSION_ID" in
            13|13.*)
                sb_kv "Version soportada" "$VERSION_ID (trixie) — objetivo"
                ;;
            12|12.*)
                logger_warn "OS version es $VERSION_ID (bookworm). El objetivo es 13 (trixie). Continuando..."
                ;;
            *)
                logger_error "OS version no soportada: $VERSION_ID (esperado 13/trixie o 12/bookworm)"
                failures=$((failures + 1))
                ;;
        esac
    else
        logger_error "/etc/os-release no existe"
        failures=$((failures + 1))
    fi

    # 2. Root
    sb_kv "Usuario actual" "$(id -un) (uid=$(id -u))"
    if [[ "$(id -u)" -ne 0 ]]; then
        logger_error "No se está ejecutando como root"
        logger_error "El operador necesita root (systemctl, ufw, apt, docker)."
        logger_error "Corrólo como root con 'su -' o con 'sudo scripts/operator.sh --all'."
        failures=$((failures + 1))
    fi

    # 3. sudo disponible
    #    OJO: `sudo` NO se usa para instalar sudo (obvio). Se instala para que
    #    el usuario songbird_admin pueda usar su sudoers scoped, y para que
    #    las ejecuciones siguientes del operador no requieran `su -`.
    if command -v sudo >/dev/null 2>&1; then
        sb_kv "sudo" "disponible"
    else
        sb_kv "sudo" "NO DISPONIBLE"
        logger_warn "sudo no está instalado: el módulo 'deps' lo instala (el usuario songbird_admin lo necesita para su sudoers scoped)."
    fi

    # 4. apt-get y systemctl
    for bin in apt-get systemctl ss; do
        if command -v "$bin" >/dev/null 2>&1; then
            sb_kv "$bin" "OK"
        else
            logger_error "No se encontró $bin en PATH"
            failures=$((failures + 1))
        fi
    done

    # 5. Toolchain base (la instala el modulo `deps`, que corre antes).
    #    Si algo falta aca, es que `deps` no se ejecuto o fallo.
    local tool
    local tools_ok=true
    for tool in jq git rsync curl openssl logrotate; do
        if ! command -v "$tool" >/dev/null 2>&1; then
            logger_error "Falta la toolchain base: $tool (ejecutá: sudo scripts/operator.sh --module deps)"
            tools_ok=false
            failures=$((failures + 1))
        fi
    done
    if [[ "$tools_ok" == "true" ]]; then
        sb_kv "Toolchain base" "jq git rsync curl openssl logrotate — OK"
    fi

    # 6. DNS
    if getent hosts deb.debian.org >/dev/null 2>&1; then
        sb_kv "DNS deb.debian.org" "OK"
    else
        logger_error "No se resuelve deb.debian.org (¿red/DNS?)"
        failures=$((failures + 1))
    fi

    # 7. Hostname, IP
    sb_kv "Hostname" "$(hostname)"
    sb_kv "FQDN" "$(hostname -f 2>/dev/null || echo 'n/a')"
    local ip
    ip="$(ip -4 addr show 2>/dev/null | awk '/inet /{print $2}' | head -n1 || echo 'n/a')"
    sb_kv "IPv4 principal" "$ip"

    # 8. Puertos objetivo
    for port in "${SB_SSH_PORT}" "${SB_APP_PORT}"; do
        if ss -ltn "sport = :$port" 2>/dev/null | grep -q ":$port"; then
            logger_warn "Puerto $port ya está en uso (alguien escucha ahí)"
        else
            sb_kv "Puerto $port" "libre"
        fi
    done

    # 9. Origen del proyecto: el checkout donde vive el script.
    if sb_is_project_dir "$SB_SOURCE_DIR"; then
        sb_kv "Checkout" "$SB_SOURCE_DIR (proyecto detectado)"
    else
        logger_error "El checkout ($SB_SOURCE_DIR) no tiene ${SB_COMPOSE_FILE_NAME} ni .env.example."
        logger_error "El operador NO clona: cloná el proyecto y corré el operador desde el checkout."
        failures=$((failures + 1))
    fi
    sb_kv "Directorio deploy" "$SB_REPO_DIR"

    # 10. Paths host
    sb_kv "State file" "$SB_STATE_FILE"
    sb_kv "Log file" "$SB_LOG_FILE"

    if [[ $failures -gt 0 ]]; then
        logger_error "Preflight falló con $failures errores"
        return 64
    fi

    state_set_module_completed preflight '{"os": "debian/'"${VERSION_ID}"'"}'
    logger_ok "Preflight OK"
}

mod_preflight_status() {
    if [[ -f "$SB_STATE_FILE" ]]; then
        state_get preflight completed_at
    fi
}
