#!/usr/bin/env bash
# scripts/lib/_lib.sh
# Helpers compartidos por todos los módulos de scripts/operator.sh
# Cargado con `source` por operator.sh antes de los módulos.

set -euo pipefail
IFS=$'\n\t'

# ----------------------------------------------------------------------------
# Versionado
# ----------------------------------------------------------------------------
readonly SB_VERSION="0.1.0"
readonly SB_NAME="songbird-operator"
readonly SB_REPO_DEFAULT="https://github.com/nikolasribeiro/proyecto-final-egreso-2026.git"
readonly SB_REPO_DIR_DEFAULT="/opt/songbird-app"
readonly SB_SSH_PORT_DEFAULT=2222
readonly SB_APP_PORT_DEFAULT=8000
readonly SB_ZABBIX_AGENT_PORT=10050
readonly SB_ZABBIX_SERVER_PORT=10051
readonly SB_ADMIN_USER_DEFAULT="songbird_admin"
readonly SB_STATE_DIR="/var/lib/songbird-operator"
readonly SB_STATE_FILE="${SB_STATE_DIR}/state.json"
readonly SB_LOG_DIR="/var/log/songbird"
readonly SB_LOG_FILE="${SB_LOG_DIR}/songbird-operator.log"
readonly SB_CRED_FILE="/opt/songbird-operator/credentials.env"
readonly SB_TEMPLATES_DIR="${SCRIPT_TEMPLATES_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/templates}"
readonly SB_LIB_DIR="${SCRIPT_LIB_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)}"

# ----------------------------------------------------------------------------
# Flags (seteados por operator.sh antes de source _lib.sh)
# ----------------------------------------------------------------------------
SB_DRY_RUN="${SB_DRY_RUN:-false}"
SB_INTERACTIVE="${SB_INTERACTIVE:-false}"
SB_NON_INTERACTIVE="${SB_NON_INTERACTIVE:-true}"
SB_SSH_PORT="${SB_SSH_PORT:-${SB_SSH_PORT_DEFAULT}}"
SB_APP_PORT="${SB_APP_PORT:-${SB_APP_PORT_DEFAULT}}"
SB_REPO_DIR="${SB_REPO_DIR:-${SB_REPO_DIR_DEFAULT}}"
SB_ADMIN_USER="${SB_ADMIN_USER:-${SB_ADMIN_USER_DEFAULT}}"
SB_EXPOSE_ZABBIX_WEB="${SB_EXPOSE_ZABBIX_WEB:-false}"
SB_ZABBIX_WEB_PORT="${SB_ZABBIX_WEB_PORT:-8080}"

# ----------------------------------------------------------------------------
# Colores (solo si stdout es TTY)
# ----------------------------------------------------------------------------
if [[ -t 1 ]]; then
    readonly C_RESET=$'\033[0m'
    readonly C_BOLD=$'\033[1m'
    readonly C_DIM=$'\033[2m'
    readonly C_RED=$'\033[31m'
    readonly C_GREEN=$'\033[32m'
    readonly C_YELLOW=$'\033[33m'
    readonly C_BLUE=$'\033[34m'
    readonly C_MAGENTA=$'\033[35m'
    readonly C_CYAN=$'\033[36m'
else
    readonly C_RESET=""
    readonly C_BOLD=""
    readonly C_DIM=""
    readonly C_RED=""
    readonly C_GREEN=""
    readonly C_YELLOW=""
    readonly C_BLUE=""
    readonly C_MAGENTA=""
    readonly C_CYAN=""
fi

# ----------------------------------------------------------------------------
# Inicialización de paths en host
# ----------------------------------------------------------------------------
sb_init_paths() {
    if [[ "$SB_DRY_RUN" == "true" ]]; then
        return 0
    fi
    install -d -m 755 -o root -g root "$SB_STATE_DIR" 2>/dev/null || true
    install -d -m 750 -g adm "$SB_LOG_DIR" 2>/dev/null || true
    install -d -m 700 -o root -g root /opt/songbird-operator 2>/dev/null || true
    install -d -m 700 -o root -g root /etc/ssh/sshd_config.d 2>/dev/null || true
    install -d -m 755 -o root -g root /etc/sudoers.d 2>/dev/null || true
    touch "$SB_LOG_FILE" 2>/dev/null || true
    chmod 640 "$SB_LOG_FILE" 2>/dev/null || true
    chown root:adm "$SB_LOG_FILE" 2>/dev/null || true
}

# ----------------------------------------------------------------------------
# Logger
# ----------------------------------------------------------------------------
_logger() {
    local level="$1"; shift
    local msg="$*"
    local ts
    ts="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    local color=""

    case "$level" in
        INFO)  color="$C_CYAN" ;;
        OK)    color="$C_GREEN" ;;
        WARN)  color="$C_YELLOW" ;;
        ERROR) color="$C_RED" ;;
        DRY)   color="$C_MAGENTA" ;;
        DEBUG) color="$C_DIM" ;;
        *)     color="" ;;
    esac

    if [[ -n "$color" ]]; then
        printf '%s %s%-5s%s %s\n' "$ts" "$color" "$level" "$C_RESET" "$msg" >&2
    else
        printf '%s %-5s %s\n' "$ts" "$level" "$msg" >&2
    fi

    # Persistir a archivo (menos colores)
    if [[ "$SB_DRY_RUN" != "true" && -w "$SB_LOG_DIR" ]]; then
        printf '%s %-5s %s\n' "$ts" "$level" "$msg" >> "$SB_LOG_FILE" 2>/dev/null || true
    fi
}

logger_info()  { _logger INFO  "$@"; }
logger_ok()    { _logger OK    "$@"; }
logger_warn()  { _logger WARN  "$@"; }
logger_error() { _logger ERROR "$@" >&2; }
logger_dry()   { _logger DRY   "$@"; }
logger_debug() {
    if [[ "${SB_DEBUG:-false}" == "true" ]]; then
        _logger DEBUG "$@"
    fi
}

# Header visual para secciones
logger_section() {
    local title="$*"
    printf '\n%s%s── %s ──%s\n' "$C_BOLD" "$C_BLUE" "$title" "$C_RESET" >&2
}

# ----------------------------------------------------------------------------
# Ejecución con soporte dry-run
# ----------------------------------------------------------------------------
run_cmd() {
    if [[ "$SB_DRY_RUN" == "true" ]]; then
        logger_dry "$*"
        return 0
    fi
    logger_debug "exec: $*"
    "$@"
}

# ----------------------------------------------------------------------------
# Estado persistente (idempotencia)
# ----------------------------------------------------------------------------
state_init() {
    if [[ -f "$SB_STATE_FILE" ]]; then
        return 0
    fi
    if [[ "$SB_DRY_RUN" == "true" ]]; then
        return 0
    fi
    sb_init_paths
    cat > "$SB_STATE_FILE" <<EOF
{
  "version": "$SB_VERSION",
  "hostname": "$(hostname)",
  "started_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "modules": {}
}
EOF
    chmod 644 "$SB_STATE_FILE"
    chown root:root "$SB_STATE_FILE"
}

state_get() {
    local module="$1"
    local key="${2:-}"
    if [[ ! -f "$SB_STATE_FILE" ]]; then
        return 1
    fi
    if [[ -z "$key" ]]; then
        jq -e --arg m "$module" '.modules[$m]' "$SB_STATE_FILE" 2>/dev/null
    else
        jq -r --arg m "$module" --arg k "$key" '.modules[$m][$k] // empty' "$SB_STATE_FILE" 2>/dev/null
    fi
}

state_set_module_completed() {
    local module="$1"
    local module_data="${2:-"{}"}"

    if [[ "$SB_DRY_RUN" == "true" ]]; then
        logger_dry "state_set_module_completed $module"
        return 0
    fi
    sb_init_paths
    state_init

    local now
    now="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

    local tmp
    tmp="$(mktemp)"
    jq -S --arg m "$module" --arg now "$now" --argjson d "$module_data" \
        '.modules[$m] = ($d + {completed_at: $now})' \
        "$SB_STATE_FILE" > "$tmp" 2>/dev/null && mv "$tmp" "$SB_STATE_FILE"
    rm -f "$tmp"
}

state_clear_module() {
    local module="$1"
    if [[ "$SB_DRY_RUN" == "true" ]]; then
        logger_dry "state_clear_module $module"
        return 0
    fi
    if [[ ! -f "$SB_STATE_FILE" ]]; then
        return 0
    fi
    local tmp
    tmp="$(mktemp)"
    jq --arg m "$module" 'del(.modules[$m])' "$SB_STATE_FILE" > "$tmp" 2>/dev/null && mv "$tmp" "$SB_STATE_FILE"
    rm -f "$tmp"
}

# Verifica si un módulo ya está aplicado y la precondición se cumple.
# Si está aplicado y la precondición está OK, imprime "skip" y retorna 0.
# Si no está aplicado o la precondición cambió, retorna 1 (caller debe aplicar).
module_skip_if_done() {
    local module="$1"
    local precondition_check="${2:-}"

    if [[ "$SB_DRY_RUN" == "true" ]]; then
        logger_dry "[$module] checking idempotency state"
    fi

    local completed_at
    completed_at="$(state_get "$module" "completed_at" 2>/dev/null || true)"

    if [[ -z "$completed_at" || "$completed_at" == "null" ]]; then
        return 1
    fi

    if [[ -n "$precondition_check" ]]; then
        if ! eval "$precondition_check" >/dev/null 2>&1; then
            logger_warn "[$module] precondición cambió, re-aplicando"
            state_clear_module "$module"
            return 1
        fi
    fi

    logger_ok "[$module] ya aplicado (skip) — última: $completed_at"
    return 0
}

# ----------------------------------------------------------------------------
# Deployment de templates con sed (delimitador | para evitar escaping de /)
# ----------------------------------------------------------------------------
deploy_template() {
    local src="$1"
    local dest="$2"
    shift 2
    # Resto de args son pares VAR=value que se sustituyen en {{VAR}}

    if [[ ! -f "$src" ]]; then
        logger_error "Template no encontrado: $src"
        return 70
    fi

    local content
    content="$(cat "$src")"

    local pair key val
    for pair in "$@"; do
        key="${pair%%=*}"
        val="${pair#*=}"
        # Escapar & y | para sed (|, & y \ son los caracteres especiales con sed -e)
        val_escaped="$(printf '%s' "$val" | sed -e 's/[&|\\]/\\&/g')"
        content="$(printf '%s' "$content" | sed -e "s|{{${key}}}|${val_escaped}|g")"
    done

    if [[ "$SB_DRY_RUN" == "true" ]]; then
        logger_dry "deploy_template $src -> $dest"
        printf '%s' "$content" | head -20
        return 0
    fi

    local dest_dir
    dest_dir="$(dirname "$dest")"
    install -d -m 755 "$dest_dir"
    printf '%s' "$content" > "$dest"
    logger_ok "Template desplegado: $dest"
}

# ----------------------------------------------------------------------------
# Verificación de root
# ----------------------------------------------------------------------------
require_root() {
    if [[ "$SB_DRY_RUN" == "true" ]]; then
        return 0
    fi
    if [[ "$(id -u)" -ne 0 ]]; then
        logger_error "Este script requiere root. Relanzando con sudo..."
        exec sudo -E "$0" "$@"
    fi
}

# ----------------------------------------------------------------------------
# Trap handlers
# ----------------------------------------------------------------------------
SB_LAST_ERROR_CODE=0
SB_LAST_ERROR_LINE=0
SB_LAST_ERROR_CMD=""
SB_TMP_DIR=""

sb_init_traps() {
    SB_TMP_DIR="$(mktemp -d)"
    trap 'on_error ${LINENO} "${BASH_COMMAND}"' ERR
    trap 'cleanup_on_exit' EXIT
}

on_error() {
    local line="$1"
    local cmd="$2"
    SB_LAST_ERROR_CODE=$?
    SB_LAST_ERROR_LINE="$line"
    SB_LAST_ERROR_CMD="$cmd"
    logger_error "Fallo en línea ${line}: ${cmd}"
    # Rollback crítico del SSH si fallamos durante su módulo
    sb_ssh_rollback_if_needed 2>/dev/null || true
}

cleanup_on_exit() {
    local exit_code=$?
    if [[ -n "$SB_TMP_DIR" && -d "$SB_TMP_DIR" ]]; then
        rm -rf "$SB_TMP_DIR"
    fi
    if [[ $exit_code -ne 0 ]]; then
        logger_error "Operador finalizado con exit code ${exit_code}"
    fi
}

# ----------------------------------------------------------------------------
# SSH rollback helpers
# ----------------------------------------------------------------------------
SB_NEEDS_SSH_ROLLBACK="false"

sb_ssh_register_rollback() {
    SB_NEEDS_SSH_ROLLBACK="true"
}

sb_ssh_rollback_if_needed() {
    if [[ "$SB_NEEDS_SSH_ROLLBACK" != "true" ]]; then
        return 0
    fi
    if [[ "$SB_DRY_RUN" == "true" ]]; then
        logger_dry "ssh rollback (drop-in)"
        return 0
    fi
    local drop_in="/etc/ssh/sshd_config.d/00-songbird-hardening.conf"
    local latest_bak
    latest_bak="$(ls -t "$drop_in".bak-* 2>/dev/null | head -n1 || true)"
    if [[ -n "$latest_bak" && -f "$latest_bak" ]]; then
        logger_warn "Restaurando SSH drop-in desde $latest_bak"
        mv "$latest_bak" "$drop_in"
        if command -v sshd >/dev/null 2>&1; then
            sshd -t && systemctl reload ssh >/dev/null 2>&1 || true
        fi
    fi
    SB_NEEDS_SSH_ROLLBACK="false"
}

# ----------------------------------------------------------------------------
# Utilidades varias
# ----------------------------------------------------------------------------

# Genera password aleatorio base64 (URL-safe)
sb_gen_password() {
    openssl rand -base64 32 | tr -d '\n'
}

# Compara version del modulo vs estado
sb_module_matches() {
    local module="$1"
    local key="$2"
    local expected="$3"
    local actual
    actual="$(state_get "$module" "$key" 2>/dev/null || true)"
    [[ "$actual" == "$expected" ]]
}

# Imprime una tabla KV simple
sb_kv() {
    local key="$1"
    local val="$2"
    printf '  %s%-22s%s %s\n' "$C_DIM" "$key" "$C_RESET" "$val"
}

# Sanity check para que el módulo pueda ser cargado sin operator.sh
# (no debe fallar en dry-run)
sb_selfcheck() {
    if [[ -z "${BASH_SOURCE[1]:-}" ]]; then
        return 0
    fi
    return 0
}

# ----------------------------------------------------------------------------
# Inicialización automática al cargar
# ----------------------------------------------------------------------------
sb_init_paths
state_init || true
sb_selfcheck
