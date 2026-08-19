#!/usr/bin/env bash
# scripts/lib/backups.sh
# Configuracion de backup automatico via cron.d (mysqldump + tar + rotacion).
# Modulo: backups

set -euo pipefail
IFS=$'\n\t'

BACKUP_CRON="/etc/cron.d/songbird-backup"
BACKUP_SCRIPT="/usr/local/sbin/songbird-backup.sh"
BACKUP_DIR="/var/backups/songbird"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-7}"
CRON_SCHEDULE="${BACKUP_CRON_SCHEDULE:-30 2 * * *}"

mod_backups_run() {
    logger_section "Módulo backups"

    if module_skip_if_done backups \
        '[[ -f '"${BACKUP_CRON}"' ]] && [[ -x '"${BACKUP_SCRIPT}"' ]]'; then
        return 0
    fi

    # 1. Crear directorio de backups
    run_cmd install -d -m 700 -o root -g root "$BACKUP_DIR"

    # 2. Desplegar script desde template
    deploy_template \
        "${SB_TEMPLATES_DIR}/backup-songbird.sh.tpl" \
        "$BACKUP_SCRIPT" \
        "BACKUP_DIR=${BACKUP_DIR}" \
        "REPO_DIR=${SB_REPO_DIR}" \
        "RETENTION_DAYS=${RETENTION_DAYS}"

    run_cmd chmod 700 "$BACKUP_SCRIPT"
    run_cmd chown root:root "$BACKUP_SCRIPT"

    # 3. Cron.d
    if [[ -f "$BACKUP_CRON" ]]; then
        run_cmd cp "$BACKUP_CRON" "$BACKUP_CRON.bak-$(date +%Y%m%d-%H%M%S)" 2>/dev/null || true
    fi
    run_cmd tee "$BACKUP_CRON" >/dev/null <<EOF
# /etc/cron.d/songbird-backup
# Gestionado por songbird-operator (issue #35).
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

${CRON_SCHEDULE} root ${BACKUP_SCRIPT} >> ${SB_LOG_FILE} 2>&1
EOF
    run_cmd chmod 644 "$BACKUP_CRON"

    # 4. Verificar que cron este corriendo
    if ! systemctl is-active cron >/dev/null 2>&1 && ! systemctl is-active crond >/dev/null 2>&1; then
        run_cmd systemctl enable --now cron 2>/dev/null || \
        run_cmd systemctl enable --now crond 2>/dev/null || \
            logger_warn "No se pudo activar cron (puede que no exista en este sistema)"
    fi

    sb_kv "Cron schedule" "$CRON_SCHEDULE"
    sb_kv "Backup dir" "$BACKUP_DIR (chmod 700)"
    sb_kv "Retention" "$RETENTION_DAYS días"
    sb_kv "Script" "$BACKUP_SCRIPT (chmod 700)"
    sb_kv "Log destination" "$SB_LOG_FILE"

    state_set_module_completed backups \
        '{"cron_schedule": "'"${CRON_SCHEDULE}"'", "retention_days": '"${RETENTION_DAYS}"', "backup_dir": "'"${BACKUP_DIR}"'"}'
    logger_ok "Backups programados"
}

mod_backups_status() {
    if [[ -f "$BACKUP_CRON" ]]; then
        echo "Cron file: $BACKUP_CRON"
        cat "$BACKUP_CRON"
    fi
    if [[ -d "$BACKUP_DIR" ]]; then
        echo ""
        echo "Backups existentes:"
        ls -lh "$BACKUP_DIR" 2>/dev/null | tail -n5 || echo "  (ninguno aún)"
    fi
}
