#!/usr/bin/env bash
# scripts/lib/deps.sh
# Instalacion de la toolchain base del host.
# Modulo: deps
#
# Este modulo es el PRIMERO del pipeline. Motivo: en una Debian 12 limpia
# (imagen genericcloud / instalacion minima) NO vienen `jq`, `git`, `curl`,
# `openssl`, `rsync` ni `logrotate`, y el operador los necesita:
#
#   jq        -> persistencia de estado (idempotencia)
#   git       -> clonar el repo si no hay checkout local
#   curl      -> healthchecks HTTP del modulo `verificar`
#   openssl   -> generacion de passwords del modulo `deploy`
#   rsync     -> sincronizacion del repo (modulo `repo`) y backups
#   logrotate -> modulo `logs`
#   iproute2  -> `ss`, usado por preflight y verificar
#   sudo      -> permite que el operador se re-eleve en el futuro
#
# Sin este modulo, el operador abortaba en el primer modulo que tocaba `jq`.

set -euo pipefail
IFS=$'\n\t'

# Paquetes base requeridos por el operador. Lista declarativa: agregar aqui
# cualquier binario que un modulo posterior necesite.
readonly SB_BASE_PACKAGES=(
    ca-certificates
    curl
    gnupg
    git
    jq
    openssl
    rsync
    logrotate
    iproute2
    sudo
)

# Binarios que el operador necesita para funcionar (verificacion post-install).
readonly SB_REQUIRED_BINARIES=(
    jq
    git
    rsync
    curl
    openssl
    logrotate
    ss
    apt-get
)

sb_deps_missing_packages() {
    local pkg
    for pkg in "${SB_BASE_PACKAGES[@]}"; do
        if ! dpkg-query -W -f='${Status}' "$pkg" 2>/dev/null | grep -q "install ok installed"; then
            printf '%s\n' "$pkg"
        fi
    done
}

mod_deps_run() {
    logger_section "Módulo deps (dependencias base del host)"

    local missing
    missing="$(sb_deps_missing_packages)"

    if [[ -z "$missing" ]]; then
        logger_ok "Las ${#SB_BASE_PACKAGES[@]} dependencias base ya están instaladas"
        state_set_module_completed deps \
            '{"packages": "'"${#SB_BASE_PACKAGES[@]}"'", "all_present": true}'
        return 0
    fi

    logger_info "Paquetes faltantes:"
    printf '%s\n' "$missing" | sed 's/^/  - /' >&2

    # apt no debe pedir confirmacion interactiva en un pipeline no-interactivo.
    export DEBIAN_FRONTEND=noninteractive

    logger_info "apt-get update..."
    run_cmd apt-get update -qq

    logger_info "Instalando dependencias base..."
    local -a to_install=()
    local pkg
    while IFS= read -r pkg; do
        [[ -n "$pkg" ]] && to_install+=("$pkg")
    done <<< "$missing"

    if [[ ${#to_install[@]} -gt 0 ]]; then
        run_cmd apt-get install -y -qq --no-install-recommends "${to_install[@]}"
    fi

    # Verificacion post-install: si falta algo, el operador no puede seguir.
    # En dry-run no se instalo nada, asi que no se verifica.
    if [[ "$SB_DRY_RUN" != "true" ]]; then
        if ! sb_require_cmd "${SB_REQUIRED_BINARIES[@]}"; then
            logger_error "Faltan dependencias obligatorias despues de instalar. Abortando."
            return 70
        fi
    fi

    local pkg_count="${#to_install[@]}"
    sb_kv "Paquetes base" "${#SB_BASE_PACKAGES[@]} (instalados ahora: ${pkg_count})"
    sb_kv "jq" "$(jq --version 2>/dev/null || echo 'n/a')"
    sb_kv "git" "$(git --version 2>/dev/null || echo 'n/a')"
    sb_kv "curl" "$(curl --version 2>/dev/null | head -n1 || echo 'n/a')"

    state_set_module_completed deps \
        '{"packages": "'"${#SB_BASE_PACKAGES[@]}"'", "installed_now": '"${pkg_count}"', "all_present": true}'
    logger_ok "Dependencias base instaladas"
}

mod_deps_status() {
    local missing
    missing="$(sb_deps_missing_packages)"
    if [[ -z "$missing" ]]; then
        echo "Todas las dependencias base están instaladas"
    else
        echo "Faltan:"
        printf '%s\n' "$missing"
    fi
}
