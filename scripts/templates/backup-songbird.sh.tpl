#!/usr/bin/env bash
# /usr/local/sbin/songbird-backup.sh
# Gestionado por songbird-operator (issue #35). Llamado por /etc/cron.d/songbird-backup.
# - Dump SQL del contenedor app-db (mariadb-dump).
# - Snapshot tar.gz del repo app (excluye .git).
# - Rotación a 7 días.

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

# --- 1. Dump de la BD app desde el contenedor ---
DB_CONTAINER="${DB_CONTAINER:-songbird_db}"
DB_NAME="${DB_NAME:-songbird}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"

if command -v docker >/dev/null 2>&1 && docker inspect "$DB_CONTAINER" >/dev/null 2>&1; then
    DB_DUMP="${BACKUP_DIR}/db-${TS}.sql.gz"
    echo "${LOG_PREFIX} Dump BD app -> ${DB_DUMP}"
    docker exec -e MYSQL_ROOT_PASSWORD="$DB_PASS" "$DB_CONTAINER" \
        sh -c "exec mariadb-dump -u${DB_USER} -p\"\${MYSQL_ROOT_PASSWORD}\" --single-transaction --routines --triggers --events ${DB_NAME}" \
        | gzip -9 > "$DB_DUMP"
    chmod 600 "$DB_DUMP"
    echo "${LOG_PREFIX} $(du -h "$DB_DUMP" | cut -f1) BD dump OK"
else
    echo "${LOG_PREFIX} WARN: contenedor $DB_CONTAINER no disponible, skip DB dump"
fi

# --- 2. Snapshot del repo (excluye .git) ---
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

# --- 3. Rotación ---
echo "${LOG_PREFIX} Rotación >${RETENTION_DAYS} días"
DELETED=$(find "$BACKUP_DIR" -type f -mtime +"$RETENTION_DAYS" -delete -print | wc -l)
echo "${LOG_PREFIX} Archivos purgados: ${DELETED}"

# --- 4. Hook off-site (opcional, no-op si BACKUP_DEST no está seteado) ---
if [[ -n "${BACKUP_DEST:-}" ]]; then
    echo "${LOG_PREFIX} Sync a destino remoto: ${BACKUP_DEST}"
    rsync -a --delete "$BACKUP_DIR/" "$BACKUP_DEST/" || \
        echo "${LOG_PREFIX} WARN: rsync a BACKUP_DEST falló"
fi

echo "${LOG_PREFIX} Backup finalizado"
