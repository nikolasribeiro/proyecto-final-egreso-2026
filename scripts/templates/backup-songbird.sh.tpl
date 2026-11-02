#!/usr/bin/env bash
# /usr/local/sbin/songbird-backup.sh
# Gestionado por songbird-operator. Llamado por /etc/cron.d/songbird-backup.
# - Dump SQL del contenedor MASTER de la app (mariadb-dump).
# - Verificacion de consistencia contra el ESCLAVO (replicacion master-slave).
# - Snapshot tar.gz del repo app (excluye .git).
# - Rotacion a N dias.

set -euo pipefail
IFS=$'\n\t'

BACKUP_DIR="{{BACKUP_DIR}}"
REPO_DIR="{{REPO_DIR}}"
RETENTION_DAYS={{RETENTION_DAYS}}
TS="$(date +%Y%m%d-%H%M%S)"
LOG_PREFIX="[songbird-backup ${TS}]"

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

echo "${LOG_PREFIX} Iniciando backup"

# --- 0. Credenciales desde el .env del proyecto desplegado --------------
ENV_FILE="${REPO_DIR}/.env"

_env_get() {
    [[ -f "$ENV_FILE" ]] || return 1
    grep -E "^$1=" "$ENV_FILE" 2>/dev/null | tail -n1 | cut -d= -f2-
}

# El dump se hace SIEMPRE contra el MASTER: es la fuente de verdad.
# Dumpear el esclavo solo serviria para respaldar datos potencialmente
# rezagados (y el esclavo es read_only).
DB_CONTAINER="${DB_CONTAINER:-songbird_db_master}"
SLAVE_CONTAINER="${SLAVE_CONTAINER:-songbird_db_slave}"
DB_NAME="${DB_NAME:-$(_env_get MYSQL_DATABASE || true)}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-$(_env_get MYSQL_ROOT_PASSWORD || true)}"

DB_NAME="${DB_NAME:-clinicas_db}"

if ! command -v docker >/dev/null 2>&1; then
    echo "${LOG_PREFIX} WARN: docker no disponible, se omite el backup de BD"
else
    # --- 1. Dump de la BD app desde el contenedor MASTER ---------------
    if docker inspect "$DB_CONTAINER" >/dev/null 2>&1; then
        DB_DUMP="${BACKUP_DIR}/db-${TS}.sql.gz"
        echo "${LOG_PREFIX} Dump BD app (master) -> ${DB_DUMP}"
        docker exec -e MYSQL_ROOT_PASSWORD="$DB_PASS" "$DB_CONTAINER" \
            sh -c "exec mariadb-dump -u${DB_USER} -p\"\${MYSQL_ROOT_PASSWORD}\" --single-transaction --routines --triggers --events ${DB_NAME}" \
            | gzip -9 > "$DB_DUMP"
        chmod 600 "$DB_DUMP"
        echo "${LOG_PREFIX} $(du -h "$DB_DUMP" | cut -f1) BD dump OK"
    else
        echo "${LOG_PREFIX} WARN: contenedor master $DB_CONTAINER no disponible, skip DB dump"
    fi

    # --- 2. Verificacion de consistencia master/slave -------------------
    if docker inspect "$SLAVE_CONTAINER" >/dev/null 2>&1; then
        MASTER_TABLES="$(docker exec -e MYSQL_ROOT_PASSWORD="$DB_PASS" "$DB_CONTAINER" \
            mariadb -uroot -p"${DB_PASS}" --protocol=tcp -h 127.0.0.1 -N -B \
            -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}';" 2>/dev/null || echo "")"
        SLAVE_TABLES="$(docker exec -e MYSQL_ROOT_PASSWORD="$DB_PASS" "$SLAVE_CONTAINER" \
            mariadb -uroot -p"${DB_PASS}" --protocol=tcp -h 127.0.0.1 -N -B \
            -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}';" 2>/dev/null || echo "")"
        if [[ -n "$MASTER_TABLES" && -n "$SLAVE_TABLES" ]]; then
            if [[ "$MASTER_TABLES" == "$SLAVE_TABLES" ]]; then
                echo "${LOG_PREFIX} OK replicacion: ${SLAVE_TABLES} tablas en master y slave"
            else
                echo "${LOG_PREFIX} WARN replicacion: master=${MASTER_TABLES} tablas, slave=${SLAVE_TABLES} tablas (replica atrasada?)"
            fi
        else
            echo "${LOG_PREFIX} WARN: no se pudo comparar master/slave"
        fi
    fi
fi

# --- 3. Snapshot del repo (excluye .git) ---
if [[ -d "$REPO_DIR" ]]; then
    REPO_SNAP="${BACKUP_DIR}/repo-${TS}.tar.gz"
    echo "${LOG_PREFIX} Snapshot repo -> ${REPO_SNAP}"
    tar -czf "$REPO_SNAP" -C "$(dirname "$REPO_DIR")" \
        --exclude='.git' \
        --exclude='.env' \
        --exclude='node_modules' \
        --exclude='vendor' \
        "$(basename "$REPO_DIR")"
    chmod 600 "$REPO_SNAP"
    echo "${LOG_PREFIX} $(du -h "$REPO_SNAP" | cut -f1) repo snapshot OK"
else
    echo "${LOG_PREFIX} WARN: $REPO_DIR no existe, skip repo snapshot"
fi

# --- 4. Rotación ---
echo "${LOG_PREFIX} Rotación >${RETENTION_DAYS} días"
DELETED=$(find "$BACKUP_DIR" -type f -mtime +"$RETENTION_DAYS" -delete -print | wc -l)
echo "${LOG_PREFIX} Archivos purgados: ${DELETED}"

# --- 5. Hook off-site (opcional, no-op si BACKUP_DEST no está seteado) ---
if [[ -n "${BACKUP_DEST:-}" ]]; then
    echo "${LOG_PREFIX} Sync a destino remoto: ${BACKUP_DEST}"
    rsync -a --delete "$BACKUP_DIR/" "$BACKUP_DEST/" || \
        echo "${LOG_PREFIX} WARN: rsync a BACKUP_DEST falló"
fi

echo "${LOG_PREFIX} Backup finalizado"
