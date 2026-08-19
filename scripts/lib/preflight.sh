#!/usr/bin/env bash
# scripts/lib/preflight.sh
# Verificación del entorno: OS, root, DNS, puertos libres, docker.sock.
# Modulo: preflight

set -euo pipefail
IFS=$'\n\t'

mod_preflight_run() {
    logger_section "Módulo preflight"

    if module_skip_if_done preflight \
        '[[ "$(. /etc/os-release && echo "$ID")" == "debian" ]] && [[ "$(. /etc/os-release && echo "$VERSION_ID")" == "12" ]] && [[ "$(id -u)" == "0" ]]'; then
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
        if [[ "$VERSION_ID" != "12" && "$VERSION_ID" != "12."* ]]; then
            logger_warn "OS version es $VERSION_ID (recomendado 12). Continuando..."
        fi
    else
        logger_error "/etc/os-release no existe"
        failures=$((failures + 1))
    fi

    # 2. Root
    sb_kv "Usuario actual" "$(id -un) (uid=$(id -u))"
    if [[ "$(id -u)" -ne 0 ]]; then
        logger_error "No se está ejecutando como root"
        failures=$((failures + 1))
    fi

    # 3. sudo disponible
    if command -v sudo >/dev/null 2>&1; then
        sb_kv "sudo" "disponible"
    else
        sb_kv "sudo" "NO DISPONIBLE"
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

    # 5. DNS
    if getent hosts deb.debian.org >/dev/null 2>&1; then
        sb_kv "DNS deb.debian.org" "OK"
    else
        logger_error "No se resuelve deb.debian.org (¿red/DNS?)"
        failures=$((failures + 1))
    fi

    # 6. Hostname, IP
    sb_kv "Hostname" "$(hostname)"
    sb_kv "FQDN" "$(hostname -f 2>/dev/null || echo 'n/a')"
    local ip
    ip="$(ip -4 addr show 2>/dev/null | awk '/inet /{print $2}' | head -n1 || echo 'n/a')"
    sb_kv "IPv4 principal" "$ip"

    # 7. Puertos objetivo
    for port in "${SB_SSH_PORT}" "${SB_APP_PORT}"; do
        if ss -ltn "sport = :$port" 2>/dev/null | grep -q ":$port"; then
            logger_warn "Puerto $port ya está en uso (alguien escucha ahí)"
        else
            sb_kv "Puerto $port" "libre"
        fi
    done

    # 8. Paths host
    sb_kv "State file" "$SB_STATE_FILE"
    sb_kv "Log file" "$SB_LOG_FILE"
    sb_kv "Repo dir" "$SB_REPO_DIR"

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
