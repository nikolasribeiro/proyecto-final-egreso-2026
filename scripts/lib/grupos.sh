#!/usr/bin/env bash
# scripts/lib/grupos.sh
# Creacion de grupos y sudoers scoped para songbird_admin.
# Modulo: grupos

set -euo pipefail
IFS=$'\n\t'

mod_grupos_run() {
    logger_section "Módulo grupos"

    if module_skip_if_done grupos \
        'getent group docker && getent group backup && getent group songbird-ops && visudo -c -f /etc/sudoers.d/songbird_admin >/dev/null 2>&1'; then
        return 0
    fi

    # 1. Grupos requeridos
    for g in docker backup songbird-ops; do
        if getent group "$g" >/dev/null 2>&1; then
            logger_ok "Grupo $g ya existe"
        else
            run_cmd groupadd "$g"
            logger_ok "Grupo $g creado"
        fi
    done

    # 2. Asignar grupos a usuarios
    run_cmd usermod -aG docker "$SB_ADMIN_USER" songbird_app || true
    run_cmd usermod -aG backup songbird_backup || true
    run_cmd usermod -aG songbird-ops "$SB_ADMIN_USER" songbird_app songbird_backup songbird_zbx || true

    # 3. Sudoers scoped para el admin
    local sudoers_file="/etc/sudoers.d/songbird_admin"
    run_cmd cp "$sudoers_file" "$sudoers_file.bak-$(date +%Y%m%d-%H%M%S)" 2>/dev/null || true

    deploy_template \
        "${SB_TEMPLATES_DIR}/sudoers_songbird_admin.tpl" \
        "$sudoers_file" \
        "ADMIN_USER=${SB_ADMIN_USER}"

    run_cmd chmod 440 "$sudoers_file"

    # 4. Validar sudoers SIEMPRE antes de aplicar
    if ! run_cmd visudo -c -f "$sudoers_file"; then
        logger_error "Sudoers inválido, restaurando backup"
        local bak
        bak="$(ls -t "$sudoers_file".bak-* 2>/dev/null | head -n1 || true)"
        if [[ -n "$bak" ]]; then
            run_cmd cp "$bak" "$sudoers_file"
        fi
        return 70
    fi

    sb_kv "sudoers file" "$sudoers_file (validado)"
    sb_kv "admin group" "$SB_ADMIN_USER -> sudo, docker, songbird-ops"

    state_set_module_completed grupos '{"admin_user": "'"${SB_ADMIN_USER}"'"}'
    logger_ok "Grupos y sudoers configurados"
}

mod_grupos_status() {
    if [[ -f "$SB_STATE_FILE" ]]; then
        state_get grupos completed_at
    fi
}
