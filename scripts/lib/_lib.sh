#!/usr/bin/env bash
# scripts/lib/_lib.sh
# Helpers compartidos por todos los módulos de scripts/operator.sh
# Cargado con `source` por operator.sh antes de los módulos.

set -euo pipefail
IFS=$'\n\t'

# ----------------------------------------------------------------------------
# Versionado
# ----------------------------------------------------------------------------
readonly SB_VERSION="0.3.0"
readonly SB_NAME="songbird-operator"
# Convención de despliegue documentada. NO es una ruta que el operador imponga:
# si invocás el script desde /opt/songbird-app, el operador trabaja ahí; si lo
# invocás desde ~/proyecto, trabaja ahí. Solo cambia con --repo-dir explícito.
readonly SB_REPO_DIR_CONVENTION="/opt/songbird-app"
# Nombre del compose file UNICO (dev + prod). El script nunca debe apuntar a
# un archivo que no exista: un compose file ausente aborta todo el `--all`.
readonly SB_COMPOSE_FILE_NAME="docker-compose.yml"
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
SB_ADMIN_USER="${SB_ADMIN_USER:-${SB_ADMIN_USER_DEFAULT}}"
SB_EXPOSE_ZABBIX_WEB="${SB_EXPOSE_ZABBIX_WEB:-false}"
SB_ZABBIX_WEB_PORT="${SB_ZABBIX_WEB_PORT:-8080}"

# Directorio del checkout desde el que se lanzo el operador.
# Es la "fuente de verdad" del despliegue: se deduce de la UBICACIÓN DE LOS
# SCRIPTS (scripts/lib/../..), NO del CWD. El operador puede invocarse desde
# cualquier directorio.
SB_SOURCE_DIR="${SB_SOURCE_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"

# Directorio EFECTIVO del proyecto. Por defecto es el repo donde vive el
# script (operación in-place). Solo cambia si se pasa --repo-dir.
SB_REPO_DIR="${SB_REPO_DIR:-${SB_SOURCE_DIR}}"

# Flags "explicitos": el operador solo pisa un valor del .env si el usuario
# lo pidió en la línea de comandos. Si no, tu configuración gana.
SB_REPO_DIR_EXPLICIT="${SB_REPO_DIR_EXPLICIT:-false}"
SB_APP_PORT_EXPLICIT="${SB_APP_PORT_EXPLICIT:-false}"
SB_ENVIRONMENT="${SB_ENVIRONMENT:-}"
SB_ENVIRONMENT_EXPLICIT="${SB_ENVIRONMENT_EXPLICIT:-false}"
# Ruta a una llave publica (.pub) para instalar en el authorized_keys del
# administrador. Vacio = deteccion automatica.
SB_SSH_KEY_PATH="${SB_SSH_KEY_PATH:-}"
# Usuarios extra autorizados a entrar por SSH (ademas del administrador).
# Space-separated, tal como lo espera la directiva AllowUsers.
SB_EXTRA_ALLOW_USERS="${SB_EXTRA_ALLOW_USERS:-}"

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
    # `IFS` del proyecto es $'\n\t' (sin espacio): "$*" uniria los argumentos
    # con salto de linea. Se arma el mensaje a mano con espacios.
    local msg="$1"
    shift
    if [[ $# -gt 0 ]]; then
        msg+=" $*"
    fi
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
# Concatena argumentos en una string legible (separados por espacio).
# "$*" no sirve: con `IFS=$'\n\t'` uniria con salto de linea.
sb_cmd_str() {
    local out=""
    local a
    for a in "$@"; do
        out+="${out:+ }${a}"
    done
    printf '%s' "$out"
}

run_cmd() {
    if [[ "$SB_DRY_RUN" == "true" ]]; then
        logger_dry "$(sb_cmd_str "$@")"
        return 0
    fi
    logger_debug "exec: $(sb_cmd_str "$@")"
    "$@"
}

# ----------------------------------------------------------------------------
# Estado persistente (idempotencia)
# ----------------------------------------------------------------------------
# `jq` es una dependencia dura del estado. En una Debian minima puede faltar,
# y `jq` ausente hacia que `jq ... && mv ...` devuelva != 0 lo que, combinado
# con `set -e`, abortaba TODO el run del operador en el primer modulo.
# Por eso: si `jq` no esta, el estado se desactiva con warning en vez de
# romper la ejecucion. El modulo `deps` instala `jq` justamente para que
# esto no llegue a pasar.
sb_has_jq() {
    command -v jq >/dev/null 2>&1
}

state_init() {
    if [[ -f "$SB_STATE_FILE" ]]; then
        return 0
    fi
    if [[ "$SB_DRY_RUN" == "true" ]]; then
        return 0
    fi
    sb_init_paths

    if ! cat > "$SB_STATE_FILE" <<EOF
{
  "version": "$SB_VERSION",
  "hostname": "$(hostname)",
  "started_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "modules": {}
}
EOF
    then
        # Sin permisos (p.ej. el operador se sourcea sin ser root): se avisa
        # pero NO se aborta el run. Perder el estado degrada la idempotencia,
        # no impide configurar el host.
        logger_warn "No se pudo crear $SB_STATE_FILE (permisos). El estado no se persistirá."
        return 0
    fi

    chmod 644 "$SB_STATE_FILE" 2>/dev/null || true
    chown root:root "$SB_STATE_FILE" 2>/dev/null || true
    return 0
}

state_get() {
    local module="$1"
    local key="${2:-}"
    if ! sb_has_jq; then
        return 1
    fi
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
    if ! sb_has_jq; then
        logger_warn "[$module] jq no disponible: no se persiste el estado (idempotencia degradada)"
        return 0
    fi
    sb_init_paths
    state_init

    local now
    now="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

    local tmp
    tmp="$(mktemp)"
    # `jq ... && mv ...` devuelve != 0 si jq falla -> con `set -e` abortaba el
    # operador. Ahora el fallo se captura explicitamente.
    if jq -S --arg m "$module" --arg now "$now" --argjson d "$module_data" \
        '.modules[$m] = ($d + {completed_at: $now})' \
        "$SB_STATE_FILE" > "$tmp" 2>/dev/null; then
        mv "$tmp" "$SB_STATE_FILE"
    else
        logger_warn "[$module] no se pudo actualizar el estado (jq fallo)"
    fi
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
    elif [[ -f "$drop_in" ]]; then
        # No habia drop-in previo (primer despliegue): se elimina el recien
        # desplegado. Si se lo deja, el proximo restart de sshd aplicaria un
        # drop-in NUNCA validado (p.ej. puerto 2222 + AllowUsers) y el host
        # quedaria sin acceso por el puerto habitual.
        logger_warn "No habia drop-in previo: se elimina el recien desplegado"
        rm -f "$drop_in"
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

# Verifica que un binario exista. Devuelve 1 (sin imprimir) si falta.
sb_require_cmd() {
    local cmd
    for cmd in "$@"; do
        if ! command -v "$cmd" >/dev/null 2>&1; then
            logger_error "Falta el binario requerido: $cmd"
            return 1
        fi
    done
    return 0
}

# Resuelve la ruta real (symlinks) de un path. Si no existe, lo devuelve tal cual.
sb_realpath() {
    local p="$1"
    if [[ -e "$p" ]]; then
        readlink -f "$p"
    else
        printf '%s' "$p"
    fi
}

# Crea un directorio con el modo/owner pedido. Si no hay permisos para cambiar
# el owner (operador sin root), degrada a mkdir -p en vez de abortar.
sb_ensure_dir() {
    local dir="$1" mode="${2:-755}" owner="${3:-root}" group="${4:-root}"

    if [[ "$SB_DRY_RUN" == "true" ]]; then
        logger_dry "install -d -m ${mode} -o ${owner} -g ${group} ${dir}"
        return 0
    fi

    if install -d -m "$mode" -o "$owner" -g "$group" "$dir" 2>/dev/null; then
        return 0
    fi
    if install -d -m "$mode" "$dir" 2>/dev/null; then
        logger_warn "No se pudo fijar owner ${owner}:${group} en ${dir} (permisos)"
        return 0
    fi
    mkdir -p "$dir"
}

# ¿El directorio indicado contiene el proyecto?
# Un "proyecto" = tiene el compose file y el .env.example.
sb_is_project_dir() {
    local dir="$1"
    [[ -f "${dir}/${SB_COMPOSE_FILE_NAME}" && -f "${dir}/.env.example" ]]
}

# ----------------------------------------------------------------------------
# Helpers de .env
# ----------------------------------------------------------------------------

# Lee el valor de una clave del .env (última definición gana).
# Strip de comentarios en línea igual que hace Docker Compose
# (`PMA_HOST=db # <- comentario` -> valor `db`).
sb_env_get() {
    local file="$1" key="$2"
    [[ -f "$file" ]] || return 1
    # `|| true` al final: con `set -o pipefail`, un grep sin coincidencias
    # devolveria != 0 y el llamador (una asignacion) abortaria el script.
    grep -E "^${key}=" "$file" 2>/dev/null | tail -n1 | cut -d= -f2- \
        | sed -e 's/[[:space:]]#.*$//' -e 's/[[:space:]]*$//' || true
}

# Escribe (o actualiza) una clave del .env preservando el resto del archivo.
# Escapa los caracteres especiales de sed (& | \). Las passwords generadas son
# base64 y pueden contener "/" y "+", por eso el escape es obligatorio.
sb_env_set() {
    local file="$1" key="$2" value="$3"
    local escaped
    escaped="$(printf '%s' "$value" | sed -e 's/[&|\\]/\\&/g')"

    if grep -qE "^${key}=" "$file" 2>/dev/null; then
        sed -i "s|^${key}=.*|${key}=${escaped}|" "$file"
    else
        # Si el archivo no termina en newline, la clave nueva se pegaria a la
        # ultima linea. Esto SIEMPRE pasa con el .env.example del repo, que
        # termina con `ZABBIX_BASE_URL=...` sin salto de linea: el resultado
        # era `ZABBIX_BASE_URL=http://.../zabbixMYSQL_REPLICATION_PASSWORD=...`
        if [[ -s "$file" ]]; then
            if [[ -n "$(tail -c 1 "$file")" ]]; then
                printf '\n' >> "$file"
            fi
        fi
        printf '%s=%s\n' "$key" "$value" >> "$file"
    fi
}

# Devuelve 0 si la clave existe en el .env con un valor NO vacío.
sb_env_has_value() {
    local file="$1" key="$2"
    local v
    v="$(sb_env_get "$file" "$key" 2>/dev/null || true)"
    [[ -n "$v" ]]
}

# ----------------------------------------------------------------------------
# Helpers de Docker Compose
# ----------------------------------------------------------------------------

# Ruta absoluta del compose file dentro del repo desplegado.
sb_compose_file() {
    printf '%s/%s' "${SB_REPO_DIR}" "${SB_COMPOSE_FILE_NAME}"
}

# `docker compose ps -a --format json`.
#
# El `-a` es IMPRESCINDIBLE: sin el, `docker compose ps` solo lista los
# contenedores CORRIENTES, y los contenedores one-shot (que ya terminaron,
# como el inicializador de replicacion) NUNCA aparecen. Como el chequeo de
# servicios pendientes compara contra `config --services` (que si lista el
# servicio), el one-shot quedaba como "pendiente" para siempre y el deploy
# esperaba el timeout completo.
#
# Compose >= 2.21 devuelve un ARRAY JSON; versiones anteriores devolvian un
# objeto JSON por linea. El helper normaliza a un stream de objetos.
sb_compose_ps_json() {
    local compose_file="$1" env_file="$2"
    docker compose -f "$compose_file" --env-file "$env_file" \
        ps -a --format json 2>/dev/null
}

# Servicios del proyecto que ya estan en un estado terminal aceptable:
#   - running + healthy (o sin healthcheck definido)
#   - exited con codigo 0 (contenedores one-shot, p.ej. el init de replicacion)
#
# Si hay varios contenedores para el mismo servicio (p.ej. uno viejo que
# quedo exited y el nuevo), se toma el MAS RECIENTE (CreatedAt) para no dar
# por bueno un contenedor que ya fue reemplazado.
sb_compose_services_ok() {
    local compose_file="$1" env_file="$2"
    sb_compose_ps_json "$compose_file" "$env_file" | jq -r '
        [ (if type == "array" then .[] else . end) ]
        | group_by(.Service)
        | map(max_by(.CreatedAt // ""))
        | map(
              select(
                    (((.State // "") == "running")
                     and (((.Health // "") == "healthy") or ((.Health // "") == "")))
                    or
                    (((.State // "") == "exited")
                     and ((((.ExitCode // 0) | tostring) == "0")))
              )
            )
        | map(.Service)
        | .[]
    ' 2>/dev/null | sort -u
}

# Servicios declarados en el compose file que aun NO estan en estado OK.
sb_compose_pending_services() {
    local compose_file="$1" env_file="$2"

    local all_services ok_services
    # `|| true`: si docker falla, la lista queda vacia y el modulo lo reporta
    # como error claro en vez de abortar con un error generico.
    all_services="$(docker compose -f "$compose_file" --env-file "$env_file" \
        config --services 2>/dev/null | sort -u || true)"
    ok_services="$(sb_compose_services_ok "$compose_file" "$env_file" || true)"

    if [[ -z "$all_services" ]]; then
        printf '(no se pudo leer el compose file)\n'
        return 1
    fi

    comm -23 \
        <(printf '%s\n' "$all_services") \
        <(if [[ -n "$ok_services" ]]; then printf '%s\n' "$ok_services"; fi)
}

# Nombre del contenedor de un servicio, resuelto desde el compose file.
# Evita hardcodear nombres de contenedor en los modulos: si el compose
# renombra `container_name`, los checks siguen funcionando.
sb_compose_container_of_service() {
    local compose_file="$1" env_file="$2" service="$3"

    local name
    name="$(sb_compose_ps_json "$compose_file" "$env_file" | jq -r --arg s "$service" '
        (if type == "array" then .[] else . end)
        | select(.Service == $s)
        | .Name
    ' 2>/dev/null | head -n1 || true)"

    if [[ -z "$name" ]]; then
        name="$(docker compose -f "$compose_file" --env-file "$env_file" \
            config --format json 2>/dev/null | jq -r --arg s "$service" \
            '.services[$s].container_name // empty' 2>/dev/null | head -n1 || true)"
    fi

    printf '%s' "$name"
}

# Espera a que todos los servicios queden OK. Devuelve 1 si se agota el timeout.
#   $1 compose file, $2 env file, $3 timeout en segundos, $4 deadline en segundos
sb_compose_wait_healthy() {
    local compose_file="$1" env_file="$2" timeout="${3:-300}" deadline="${4:-600}"
    local waited=0
    local pending

    while [[ "$waited" -lt "$timeout" ]]; do
        pending="$(sb_compose_pending_services "$compose_file" "$env_file" || true)"
        if [[ -z "$pending" ]]; then
            printf '\n' >&2
            return 0
        fi
        sleep 5
        waited=$((waited + 5))
        printf '.' >&2
    done
    printf '\n' >&2
    logger_warn "Timeout esperando healthchecks. Servicios pendientes:"
    printf '%s\n' "$pending" >&2
    return 1
}

# Compara version de docker compose contra un minimo (ej. "2.20").
sb_compose_version_at_least() {
    local min="$1"
    local current
    current="$(docker compose version --short 2>/dev/null | head -n1 | tr -d 'v' || true)"
    [[ -z "$current" ]] && return 1
    # compara semver simple: mayor, igual o menor
    local IFS=.
    # shellcheck disable=SC2206
    local -a c=(${current})
    # shellcheck disable=SC2206
    local -a m=(${min})
    IFS=$'\n\t'
    local i
    for i in 0 1 2; do
        local cv="${c[$i]:-0}" mv="${m[$i]:-0}"
        if [[ "$cv" -gt "$mv" ]]; then
            return 0
        fi
        if [[ "$cv" -lt "$mv" ]]; then
            return 1
        fi
    done
    return 0
}

# Resuelve el APP_PORT efectivo.
# Prioridad: --app-port (flag) > SIGSM_APP_PORT (env) > .env del proyecto >
# default. Si el usuario configuró APP_PORT a mano en su .env, ese manda.
sb_resolve_app_port() {
    if [[ "$SB_APP_PORT_EXPLICIT" == "true" ]]; then
        printf '%s' "$SB_APP_PORT"
        return 0
    fi
    if [[ -n "${SIGSM_APP_PORT:-}" ]]; then
        printf '%s' "$SIGSM_APP_PORT"
        return 0
    fi
    local env_file="${SB_REPO_DIR}/.env"
    if [[ -f "$env_file" ]]; then
        local v
        v="$(sb_env_get "$env_file" APP_PORT 2>/dev/null || true)"
        if [[ "$v" =~ ^[0-9]+$ ]]; then
            printf '%s' "$v"
            return 0
        fi
    fi
    printf '%s' "$SB_APP_PORT_DEFAULT"
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
# Llave SSH del usuario administrador
# ----------------------------------------------------------------------------
# Estrategia (en este orden):
#   1. Si se paso --ssh-key <archivo.pub>, se instala esa.
#   2. Si el usuario que invoco el operador ($SUDO_USER) tiene una llave
#      publica, se instala esa: la PRIVADA nunca toca el servidor.
#   3. Si no hay ninguna, se genera un par ed25519 EN EL SERVIDOR, dentro del
#      home del admin. El resumen final explica como traerla y borrarla.
# En cualquier caso queda registrada en <state>/ssh_key_origin para que el
# resumen final sepa que hacer.
sb_ssh_key_setup() {
    local admin_home="$1"
    local ssh_dir="${admin_home}/.ssh"
    local authorized="${ssh_dir}/authorized_keys"
    local origen=""
    local pub_key=""

    # --- 1) Llave indicada por el operador ----------------------------
    if [[ -n "${SB_SSH_KEY_PATH:-}" && -f "${SB_SSH_KEY_PATH}" ]]; then
        pub_key="$(head -n1 "${SB_SSH_KEY_PATH}" 2>/dev/null || true)"
        origen="${SB_SSH_KEY_PATH}"
    fi

    # --- 2) Llave publica del usuario que invoco el operador ----------
    if [[ -z "$pub_key" ]]; then
        local invocador="${SUDO_USER:-}"
        if [[ -n "$invocador" && "$invocador" != "root" ]] && id "$invocador" >/dev/null 2>&1; then
            local home_inv
            # `|| true`: getent devuelve 2 si el usuario no existe.
            home_inv="$(getent passwd "$invocador" 2>/dev/null | cut -d: -f6 || true)"
            local k
            for k in id_ed25519.pub id_ecdsa.pub id_rsa.pub; do
                if [[ -f "${home_inv}/.ssh/${k}" ]]; then
                    pub_key="$(head -n1 "${home_inv}/.ssh/${k}" 2>/dev/null || true)"
                    if [[ -n "$pub_key" ]]; then
                        origen="${home_inv}/.ssh/${k}"
                        break
                    fi
                fi
            done
        fi
    fi

    # --- 3) Generar el par en el servidor -----------------------------
    if [[ -z "$pub_key" ]]; then
        if [[ -f "${ssh_dir}/id_ed25519.pub" ]]; then
            # Ya existia de una corrida anterior: se reutiliza.
            pub_key="$(head -n1 "${ssh_dir}/id_ed25519.pub" 2>/dev/null || true)"
            origen="servidor"
        elif command -v ssh-keygen >/dev/null 2>&1; then
            logger_info "Generando par de llaves ed25519 para $SB_ADMIN_USER en ${ssh_dir}"
            run_cmd ssh-keygen -t ed25519 -f "${ssh_dir}/id_ed25519" -N "" \
                -C "${SB_ADMIN_USER}@$(hostname)"
            pub_key="$(head -n1 "${ssh_dir}/id_ed25519.pub" 2>/dev/null || true)"
            origen="servidor"
        else
            logger_warn "ssh-keygen no está disponible: no se puede generar la llave"
            return 1
        fi
    fi

    if [[ -z "$pub_key" ]]; then
        logger_warn "No se pudo obtener una llave pública para $SB_ADMIN_USER"
        return 1
    fi

    # --- Instalar en authorized_keys (idempotente) ---------------------
    if [[ -f "$authorized" ]] && grep -qxF "$pub_key" "$authorized" 2>/dev/null; then
        logger_ok "Llave pública ya instalada en ${authorized}"
    else
        printf '%s\n' "$pub_key" >> "$authorized"
        logger_ok "Llave pública instalada en ${authorized}"
    fi

    # `|| true`: un chmod/chown fallido no debe abortar el operador entero.
    run_cmd chmod 700 "$ssh_dir" || true
    run_cmd chmod 600 "$authorized" || true
    if [[ -f "${ssh_dir}/id_ed25519" ]]; then
        run_cmd chmod 600 "${ssh_dir}/id_ed25519" || true
    fi
    run_cmd chown -R "${SB_ADMIN_USER}:${SB_ADMIN_USER}" "$ssh_dir" || true

    # Registrar el origen para el resumen final
    if [[ "$SB_DRY_RUN" != "true" ]]; then
        install -d -m 755 "$SB_STATE_DIR" 2>/dev/null || true
        printf '%s\n' "$origen" > "${SB_STATE_DIR}/ssh_key_origin" 2>/dev/null || true
    fi

    sb_kv "Llave pública" "${authorized}"
    sb_kv "Origen" "$origen"
    return 0
}


# ----------------------------------------------------------------------------
# Resumen final del despliegue
# ----------------------------------------------------------------------------
# Al terminar el operador se imprime esto: accesos, dónde están las llaves SSH,
# estado de la replicación, rutas importantes y los próximos pasos. Sin esto el
# final del run es un "listo" sin contexto.
sb_print_resumen() {
    local compose_file env_file host_ip
    compose_file="$(sb_compose_file)"
    env_file="${SB_REPO_DIR}/.env"

    host_ip="$(ip -4 addr show 2>/dev/null | awk '/inet /{print $2}' | head -n1 | cut -d/ -f1 || true)"
    if [[ -z "$host_ip" ]]; then
        host_ip="$(hostname -I 2>/dev/null | awk '{print $1}' || true)"
    fi
    [[ -z "$host_ip" ]] && host_ip="<ip-del-host>"

    local admin_home
    # `|| true`: getent devuelve 2 si el usuario no existe.
    admin_home="$(getent passwd "$SB_ADMIN_USER" 2>/dev/null | cut -d: -f6 || true)"
    if [[ -z "$admin_home" ]]; then
        admin_home="/home/${SB_ADMIN_USER}"
    fi

    printf '\n%s%s══════════════════════════════════════════════════════════════════════%s\n' \
        "$C_BOLD" "$C_CYAN" "$C_RESET" >&2
    printf '%s%s  RESUMEN DEL DESPLIEGUE — %s v%s%s\n' \
        "$C_BOLD" "$C_CYAN" "$SB_NAME" "$SB_VERSION" "$C_RESET" >&2
    printf '%s%s══════════════════════════════════════════════════════════════════════%s\n' \
        "$C_BOLD" "$C_CYAN" "$C_RESET" >&2

    # --- ACCESO ---------------------------------------------------------
    printf '\n%sACCESO%s\n' "$C_BOLD" "$C_RESET" >&2
    printf '  Aplicación       http://%s:%s/\n' "$host_ip" "$SB_APP_PORT" >&2
    printf '  Zabbix           http://%s:%s/zabbix/\n' "$host_ip" "$SB_APP_PORT" >&2
    printf '  SSH              ssh -p %s %s@%s\n' "$SB_SSH_PORT" "$SB_ADMIN_USER" "$host_ip" >&2
    printf '  Usuario / Password: %s / %s\n' "$SB_ADMIN_USER" "$SB_ADMIN_USER" >&2
    printf '  (MODO DEMO: el drop-in de SSH habilita PasswordAuthentication)\n' >&2
    if [[ -n "${SB_EXTRA_ALLOW_USERS// /}" ]]; then
        printf '  OJO: por SSH solo entran %s y%s (AllowUsers). Con la consola del\n' \
            "$SB_ADMIN_USER" "$SB_EXTRA_ALLOW_USERS" >&2
        printf '       servidor usá:  sudo cat %s/.ssh/id_ed25519\n' "$admin_home" >&2
    fi

    # --- LLAVES SSH -----------------------------------------------------
    printf '\n%sLLAVES SSH%s\n' "$C_BOLD" "$C_RESET" >&2
    local origen_llave=""
    [[ -f "${SB_STATE_DIR}/ssh_key_origin" ]] && origen_llave="$(cat "${SB_STATE_DIR}/ssh_key_origin" 2>/dev/null || true)"
    if [[ -f "${admin_home}/.ssh/id_ed25519" && "$origen_llave" == "servidor" ]]; then
        printf '  Se GENERO un par de llaves en ESTE servidor.\n' >&2
        printf '  Privada          %s/.ssh/id_ed25519\n' "$admin_home" >&2
        printf '  Publica          %s/.ssh/id_ed25519.pub\n' "$admin_home" >&2
        printf '  Para obtenerla (desde la CONSOLA del servidor):\n' >&2
        printf '    sudo cat %s/.ssh/id_ed25519\n' "$admin_home" >&2
        printf '  Copiala a un archivo en tu maquina y dale permisos 600.\n' >&2
        printf '  Despues borrala del servidor (la privada no debe quedar ahi):\n' >&2
        printf '    sudo rm -f %s/.ssh/id_ed25519\n' "$admin_home" >&2
        printf '  Y conectate con:\n' >&2
        printf '    ssh -i ~/.ssh/songbird_admin_ed25519 -p %s %s@%s\n' \
            "$SB_SSH_PORT" "$SB_ADMIN_USER" "$host_ip" >&2
    elif [[ -n "$origen_llave" ]]; then
        printf '  Llave publica instalada en %s/.ssh/authorized_keys\n' "$admin_home" >&2
        printf '  Origen           %s\n' "$origen_llave" >&2
        printf '  La llave PRIVADA nunca estuvo en este servidor.\n' >&2
    else
        printf '  No se genero ninguna llave en esta corrida.\n' >&2
        printf '  Revisa: %s/.ssh/authorized_keys\n' "$admin_home" >&2
    fi

    # --- BASE DE DATOS ---------------------------------------------------
    printf '\n%sBASE DE DATOS (master-slave)%s\n' "$C_BOLD" "$C_RESET" >&2
    local c_master c_slave estado_repl="?"
    c_master="$(sb_compose_container_of_service "$compose_file" "$env_file" db-master)"
    c_slave="$(sb_compose_container_of_service "$compose_file" "$env_file" db-slave)"
    local root_pwd
    root_pwd="$(sb_env_get "$env_file" MYSQL_ROOT_PASSWORD 2>/dev/null || true)"
    local master_vivo="false"
    if [[ -n "$c_master" ]] && docker inspect "$c_master" >/dev/null 2>&1; then
        master_vivo="true"
        if [[ -n "$root_pwd" ]]; then
            estado_repl="$(docker exec "$c_master" \
                mariadb -uroot -p"$root_pwd" --protocol=tcp -h 127.0.0.1 -N -B \
                -e "SHOW GLOBAL STATUS LIKE 'Slave_running';" 2>/dev/null | cut -f2 || true)"
        fi
    fi
    printf '  Master (escritura)  %s\n' "${c_master:-db-master}" >&2
    printf '  Slave  (lectura)    %s\n' "${c_slave:-db-slave}" >&2
    if [[ "$estado_repl" == "ON" ]]; then
        printf '  Replicación         %sACTIVA%s (hilos IO y SQL corriendo)\n' "$C_GREEN" "$C_RESET" >&2
    elif [[ "$master_vivo" == "true" ]]; then
        printf '  Replicación         %sNO ACTIVA%s (Slave_running=%s)\n' "$C_RED" "$C_RESET" "${estado_repl:-?}" >&2
        # Diagnostico: por que fallo el inicializador.
        local c_init init_exit
        c_init="$(sb_compose_container_of_service "$compose_file" "$env_file" db-replication-init)"
        if [[ -n "$c_init" ]]; then
            init_exit="$(docker inspect -f '{{.State.ExitCode}}' "$c_init" 2>/dev/null || echo '?')"
            printf '  db-replication-init  exit code %s\n' "$init_exit" >&2
            printf '  Ultimas lineas del inicializador:\n' >&2
            docker logs "$c_init" 2>&1 | tail -n 6 | sed 's/^/    /' >&2 || true
        fi
    else
        printf '  Replicación         %sno verificable%s (el contenedor master no está corriendo)\n' "$C_YELLOW" "$C_RESET" >&2
    fi
    printf '  Verificar el estado:\n' >&2
    printf '    docker compose -f %s exec db-slave mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SHOW SLAVE STATUS\\G"\n' \
        "$compose_file" >&2

    # --- RUTAS -----------------------------------------------------------
    printf '\n%sRUTAS%s\n' "$C_BOLD" "$C_RESET" >&2
    printf '  Proyecto        %s\n' "$SB_REPO_DIR" >&2
    printf '  Compose file    %s\n' "$compose_file" >&2
    printf '  Variables       %s (600)\n' "$env_file" >&2
    printf '  Credenciales    %s (600)\n' "$SB_CRED_FILE" >&2
    printf '  Log operador    %s\n' "$SB_LOG_FILE" >&2
    printf '  Backups         /var/backups/songbird/\n' >&2
    printf '  Estado (json)   %s\n' "$SB_STATE_FILE" >&2

    # --- PROXIMOS PASOS --------------------------------------------------
    printf '\n%sPROXIMOS PASOS%s\n' "$C_BOLD" "$C_RESET" >&2
    local paso=1
    if [[ -f "${admin_home}/.ssh/id_ed25519" && "$origen_llave" == "servidor" ]]; then
        printf '  %s. Traé la llave privada SSH del servidor y borrala de ahí.\n' "$paso" >&2
        paso=$((paso + 1))
    fi
    printf '  %s. Entrá a la app: http://%s:%s/\n' "$paso" "$host_ip" "$SB_APP_PORT" >&2
    paso=$((paso + 1))
    printf '  %s. Cambiá el password de Admin/zabbix en la UI de Zabbix.\n' "$paso" >&2
    paso=$((paso + 1))
    printf '  %s. Vincular los hosts en Zabbix (proxy, web, db, db_slave).\n' "$paso" >&2
    paso=$((paso + 1))
    printf '  %s. Probá el backup: sudo /usr/local/sbin/songbird-backup.sh\n' "$paso" >&2
    printf '\n  Log en vivo:  sudo tail -f %s\n' "$SB_LOG_FILE" >&2
    printf '  Smoke test:   sudo scripts/operator.sh --module verificar\n' >&2
    printf '\n' >&2
}

# ----------------------------------------------------------------------------
# Inicialización automática al cargar
# ----------------------------------------------------------------------------
sb_init_paths
state_init || true
sb_selfcheck
