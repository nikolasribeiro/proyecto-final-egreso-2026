#!/usr/bin/env bash
# scripts/operator.sh
# Entry point de songbird-operator. Implementa el issue #35.
# Modo default: no-interactive (asume defaults, aborta en error).
#
# Uso:
#   sudo scripts/operator.sh --all
#   sudo scripts/operator.sh --module ssh
#   sudo scripts/operator.sh --module verificar
#   sudo scripts/operator.sh --all --dry-run
#   sudo scripts/operator.sh --help

set -euo pipefail
IFS=$'\n\t'

# ----------------------------------------------------------------------------
# Paths
# ----------------------------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly SCRIPT_DIR
SCRIPT_TEMPLATES_DIR="${SCRIPT_DIR}/templates"
SCRIPT_LIB_DIR="${SCRIPT_DIR}/lib"

# ----------------------------------------------------------------------------
# Auto-elevacion a root
# ----------------------------------------------------------------------------
if [[ "$(id -u)" -ne 0 ]]; then
    if command -v sudo >/dev/null 2>&1; then
        exec sudo -E "$0" "$@"
    else
        echo "[FATAL] Este script requiere root (o sudo disponible)" >&2
        exit 64
    fi
fi

# ----------------------------------------------------------------------------
# Cargar _lib.sh (helpers, constantes)
# ----------------------------------------------------------------------------
# shellcheck source=lib/_lib.sh
source "${SCRIPT_LIB_DIR}/_lib.sh"

# ----------------------------------------------------------------------------
# Constantes adicionales del entry point
# ----------------------------------------------------------------------------
readonly SB_MODULES=(
    preflight
    usuarios
    grupos
    ssh
    red
    firewall
    docker
    bd
    logs
    backups
    deploy
    verificar
)

# ----------------------------------------------------------------------------
# CLI parse
# ----------------------------------------------------------------------------
SB_MODE=""
SB_MODULE=""
SB_HOSTNAME=""
SB_RESET_VOLUMES="false"
SB_UNINSTALL="false"
SB_HELP="false"
SB_VERSION="false"

usage() {
    cat <<EOF
${C_BOLD}${SB_NAME} v${SB_VERSION}${C_RESET}

Script bash modular para configurar un servidor Debian 12 con SSH endurecido,
UFW, usuarios, Docker, backups, logs y el stack del proyecto (PHP + MariaDB +
Zabbix) corriendo.

${C_BOLD}USO${C_RESET}
    sudo scripts/operator.sh --all [opciones]
    sudo scripts/operator.sh --module <nombre> [opciones]
    sudo scripts/operator.sh --help | --version

${C_BOLD}MODOS${C_RESET}
    --all                      Ejecuta los 12 modulos en orden
    --module <nombre>          Ejecuta solo un modulo (${SB_MODULES[*]})
    --uninstall                Reversa los modulos (orden inverso)

${C_BOLD}OPCIONES${C_RESET}
    --hostname <fqdn>          Cambiar hostname del host
    --ssh-port <N>             Puerto SSH (default: ${SB_SSH_PORT_DEFAULT})
    --app-port <N>             APP_PORT publicado en host (default: ${SB_APP_PORT_DEFAULT})
    --repo-dir <path>          Path al repo (default: ${SB_REPO_DIR_DEFAULT})
    --expose-zabbix-web        Abre puerto 8080 en UFW para UI Zabbix
    --reset-volumes            Borra volumenes Docker antes de deploy (DESTRUCTIVO)
    --interactive              Activa prompts de confirmacion (default: no)
    --dry-run                  Muestra comandos sin ejecutarlos
    --help | -h                Muestra esta ayuda
    --version | -V             Muestra version

${C_BOLD}VARIABLES DE ENTORNO${C_RESET}
    SIGSM_SSH_PORT, SIGSM_APP_PORT, SIGSM_REPO_DIR, SIGSM_ADMIN_USER,
    SIGSM_EXPOSE_ZABBIX_WEB, SIGSM_DRY_RUN, SIGSM_INTERACTIVE

${C_BOLD}EJEMPLOS${C_RESET}
    # Run completo (modo no-interactive, default)
    sudo scripts/operator.sh --all

    # Solo el modulo ssh
    sudo scripts/operator.sh --module ssh

    # Cambiar puerto SSH y APP_PORT
    sudo scripts/operator.sh --all --ssh-port 22022 --app-port 80

    # Dry-run para ver que haria sin tocar nada
    sudo scripts/operator.sh --all --dry-run

    # Smoke test (15 checks)
    sudo scripts/operator.sh --module verificar

${C_BOLD}MAS INFORMACION${C_RESET}
    Ver scripts/README.md para troubleshooting, limit known issues y tareas post-deploy.
EOF
}

parse_cli() {
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --all)
                SB_MODE="all"
                shift
                ;;
            --module)
                [[ $# -ge 2 ]] || { logger_error "--module requiere un argumento"; exit 64; }
                SB_MODE="single"
                SB_MODULE="$2"
                shift 2
                ;;
            --uninstall)
                SB_UNINSTALL="true"
                shift
                ;;
            --hostname)
                [[ $# -ge 2 ]] || { logger_error "--hostname requiere un argumento"; exit 64; }
                SB_HOSTNAME="$2"
                shift 2
                ;;
            --ssh-port)
                [[ $# -ge 2 ]] || { logger_error "--ssh-port requiere un argumento"; exit 64; }
                SB_SSH_PORT="$2"
                shift 2
                ;;
            --app-port)
                [[ $# -ge 2 ]] || { logger_error "--app-port requiere un argumento"; exit 64; }
                SB_APP_PORT="$2"
                shift 2
                ;;
            --repo-dir)
                [[ $# -ge 2 ]] || { logger_error "--repo-dir requiere un argumento"; exit 64; }
                SB_REPO_DIR="$2"
                shift 2
                ;;
            --expose-zabbix-web)
                SB_EXPOSE_ZABBIX_WEB="true"
                shift
                ;;
            --reset-volumes)
                SB_RESET_VOLUMES="true"
                shift
                ;;
            --interactive)
                SB_INTERACTIVE="true"
                SB_NON_INTERACTIVE="false"
                shift
                ;;
            --non-interactive)
                SB_INTERACTIVE="false"
                SB_NON_INTERACTIVE="true"
                shift
                ;;
            --dry-run)
                SB_DRY_RUN="true"
                shift
                ;;
            --help|-h)
                SB_HELP="true"
                shift
                ;;
            --version|-V)
                SB_VERSION="true"
                shift
                ;;
            *)
                logger_error "Flag desconocida: $1 (usar --help)"
                exit 64
                ;;
        esac
    done
}

# ----------------------------------------------------------------------------
# Carga perezosa de un modulo
# ----------------------------------------------------------------------------
load_module() {
    local mod="$1"
    local mod_file="${SCRIPT_LIB_DIR}/${mod}.sh"
    if [[ ! -f "$mod_file" ]]; then
        logger_error "Modulo no encontrado: $mod_file"
        exit 70
    fi
    # shellcheck source=lib/${mod}.sh
    source "$mod_file"
}

run_module() {
    local mod="$1"
    load_module "$mod"

    if ! command -v "mod_${mod}_run" >/dev/null 2>&1; then
        logger_error "Modulo $mod no expone mod_${mod}_run()"
        exit 70
    fi

    logger_info "Ejecutando modulo: $mod"
    "mod_${mod}_run"
}

# ----------------------------------------------------------------------------
# Validaciones previas
# ----------------------------------------------------------------------------
validate_args() {
    if [[ "$SB_HELP" == "true" ]]; then
        usage
        exit 0
    fi
    if [[ "$SB_VERSION" == "true" ]]; then
        printf '%s %s\n' "$SB_NAME" "$SB_VERSION"
        exit 0
    fi

    # Si no se pasa ningun modo, mostrar ayuda
    if [[ -z "$SB_MODE" && "$SB_UNINSTALL" != "true" ]]; then
        usage
        exit 0
    fi

    # --module debe ser uno valido
    if [[ "$SB_MODE" == "single" ]]; then
        local valid=false
        for m in "${SB_MODULES[@]}"; do
            if [[ "$m" == "$SB_MODULE" ]]; then
                valid=true
                break
            fi
        done
        if [[ "$valid" != "true" ]]; then
            logger_error "Modulo invalido: $SB_MODULE"
            logger_error "Modulos validos: ${SB_MODULES[*]}"
            exit 64
        fi
    fi

    # SSH port y APP port deben ser numericos
    if ! [[ "$SB_SSH_PORT" =~ ^[0-9]+$ ]] || [[ "$SB_SSH_PORT" -lt 1 ]] || [[ "$SB_SSH_PORT" -gt 65535 ]]; then
        logger_error "--ssh-port invalido: $SB_SSH_PORT"
        exit 64
    fi
    if ! [[ "$SB_APP_PORT" =~ ^[0-9]+$ ]] || [[ "$SB_APP_PORT" -lt 1 ]] || [[ "$SB_APP_PORT" -gt 65535 ]]; then
        logger_error "--app-port invalido: $SB_APP_PORT"
        exit 64
    fi

    # --reset-volumes requiere --interactive
    if [[ "$SB_RESET_VOLUMES" == "true" && "$SB_INTERACTIVE" != "true" ]]; then
        logger_error "--reset-volumes requiere --interactive (es destructivo)"
        exit 64
    fi
}

# ----------------------------------------------------------------------------
# Banner
# ----------------------------------------------------------------------------
print_banner() {
    printf '%s%s═══════════════════════════════════════════════%s\n' \
        "$C_BOLD" "$C_CYAN" "$C_RESET" >&2
    printf '%s%s  %s v%s%s\n' \
        "$C_BOLD" "$C_CYAN" "$SB_NAME" "$SB_VERSION" "$C_RESET" >&2
    printf '%s%s═══════════════════════════════════════════════%s\n\n' \
        "$C_BOLD" "$C_CYAN" "$C_RESET" >&2

    sb_kv "Modo"           "${SB_MODE:-<none>}"
    sb_kv "Modulo"          "${SB_MODULE:-<all>}"
    sb_kv "Hostname"        "${SB_HOSTNAME:-$(hostname)}"
    sb_kv "SSH port"        "$SB_SSH_PORT"
    sb_kv "APP_PORT"        "$SB_APP_PORT"
    sb_kv "Repo dir"        "$SB_REPO_DIR"
    sb_kv "Expose Zabbix"   "$SB_EXPOSE_ZABBIX_WEB"
    sb_kv "Reset volumes"   "$SB_RESET_VOLUMES"
    sb_kv "Dry-run"         "$SB_DRY_RUN"
    sb_kv "Interactive"     "$SB_INTERACTIVE"
    sb_kv "Repo (templates)" "$SB_TEMPLATES_DIR"
    printf '\n' >&2
}

# ----------------------------------------------------------------------------
# Main
# ----------------------------------------------------------------------------
main() {
    parse_cli "$@"
    validate_args
    print_banner

    # Init paths + traps
    sb_init_paths
    state_init
    sb_init_traps

    # Export para subshells (ej. docker exec)
    export SB_DRY_RUN SB_SSH_PORT SB_APP_PORT SB_REPO_DIR SB_ADMIN_USER

    # Aplicar hostname si se pidio
    if [[ -n "$SB_HOSTNAME" ]]; then
        export RED_NEW_HOSTNAME="$SB_HOSTNAME"
    fi

    # Si el operador quiere reset de volumenes (interactivo), hacerlo
    if [[ "$SB_RESET_VOLUMES" == "true" ]]; then
        logger_warn "BORRANDO VOLUMENES (mariadb_data, zabbix-db-data) — accion destructiva"
        if [[ "$SB_DRY_RUN" != "true" ]]; then
            docker volume rm mariadb_data 2>/dev/null || true
            docker volume rm zabbix-db-data 2>/dev/null || true
        else
            logger_dry "docker volume rm mariadb_data"
            logger_dry "docker volume rm zabbix-db-data"
        fi
    fi

    if [[ "$SB_UNINSTALL" == "true" ]]; then
        logger_warn "Modo uninstall: revirtiendo modulos en orden inverso"
        if [[ "$SB_INTERACTIVE" != "true" ]]; then
            logger_error "--uninstall requiere --interactive"
            exit 64
        fi
        for ((i=${#SB_MODULES[@]}-1; i>=0; i--)); do
            local mod="${SB_MODULES[i]}"
            load_module "$mod"
            if command -v "mod_${mod}_rollback" >/dev/null 2>&1; then
                logger_info "Revirtiendo: $mod"
                "mod_${mod}_rollback" || logger_warn "Rollback de $mod tuvo warnings"
            fi
        done
        logger_ok "Uninstall finalizado"
        exit 0
    fi

    if [[ "$SB_MODE" == "single" ]]; then
        run_module "$SB_MODULE"
        logger_ok "Modulo $SB_MODULE finalizado"
        exit 0
    fi

    if [[ "$SB_MODE" == "all" ]]; then
        for mod in "${SB_MODULES[@]}"; do
            run_module "$mod"
        done
        logger_ok "Songbird-operator v${SB_VERSION} finalizado"
        exit 0
    fi

    # Si llegamos aca, no se especifico modo -> ya se imprimio help y salimos
    exit 0
}

main "$@"
