#!/usr/bin/env bash
# scripts/lib/logs.sh
# Configuracion de logrotate + journal persistente.
# Modulo: logs

set -euo pipefail
IFS=$'\n\t'

LOGROTATE_FILE="/etc/logrotate.d/songbird"

mod_logs_run() {
    logger_section "Módulo logs"

    if module_skip_if_done logs \
        '[[ -f '"${LOGROTATE_FILE}"' ]] && [[ -d /var/log/journal ]]'; then
        return 0
    fi

    # 1. Journal persistente
    if [[ ! -d /var/log/journal ]]; then
        run_cmd mkdir -p /var/log/journal
        logger_ok "/var/log/journal creado (journal persistente)"
    else
        logger_ok "/var/log/journal ya existe"
    fi
    if command -v systemctl >/dev/null 2>&1; then
        run_cmd systemctl restart systemd-journald 2>/dev/null || true
    fi

    # 2. Directorio de logs de la app
    run_cmd install -d -m 750 -g adm "$SB_LOG_DIR"

    # 3. Logrotate drop-in
    if [[ -f "$LOGROTATE_FILE" ]]; then
        run_cmd cp "$LOGROTATE_FILE" "$LOGROTATE_FILE.bak-$(date +%Y%m%d-%H%M%S)" 2>/dev/null || true
    fi

    deploy_template \
        "${SB_TEMPLATES_DIR}/logrotate-songbird.tpl" \
        "$LOGROTATE_FILE"

    run_cmd chmod 644 "$LOGROTATE_FILE"

    # 4. Verificar logrotate
    if command -v logrotate >/dev/null 2>&1; then
        if run_cmd logrotate -d "$LOGROTATE_FILE" >/dev/null 2>&1; then
            sb_kv "logrotate" "OK (dry-run)"
        else
            logger_warn "logrotate -d reportó warnings (revisar)"
        fi
    fi

    local boot_count
    boot_count="$(journalctl --list-boots 2>/dev/null | wc -l)"
    sb_kv "Journal boots" "$boot_count"

    state_set_module_completed logs \
        '{"journal_persistent": true, "logrotate_file": "'"${LOGROTATE_FILE}"'"}'
    logger_ok "Logs configurados"
}

mod_logs_status() {
    if [[ -d /var/log/journal ]]; then
        echo "Journal persistente: OK"
    else
        echo "Journal persistente: NO"
    fi
    echo "Logrotate: $LOGROTATE_FILE"
}
