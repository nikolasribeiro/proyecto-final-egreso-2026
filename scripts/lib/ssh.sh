#!/usr/bin/env bash
# scripts/lib/ssh.sh
# Hardening del servicio SSH: puerto custom, sin root, sin password, key-only.
# Modulo: ssh

set -euo pipefail
IFS=$'\n\t'

SSH_DROP_IN="/etc/ssh/sshd_config.d/00-songbird-hardening.conf"

mod_ssh_run() {
    logger_section "Módulo ssh (hardening)"

    if module_skip_if_done ssh \
        '[[ -f '"${SSH_DROP_IN}"' ]] && grep -q "^Port ${SB_SSH_PORT}"'" ${SSH_DROP_IN}"' && sshd -t 2>/dev/null'; then
        return 0
    fi

    # 1. Instalar openssh-server si falta
    if ! dpkg -l openssh-server >/dev/null 2>&1; then
        logger_info "Instalando openssh-server"
        run_cmd apt-get update -qq
        run_cmd apt-get install -y -qq openssh-server
    else
        logger_ok "openssh-server ya instalado"
    fi

    # 2. Backup del drop-in actual
    if [[ -f "$SSH_DROP_IN" ]]; then
        local bak="${SSH_DROP_IN}.bak-$(date +%Y%m%d-%H%M%S)"
        run_cmd cp "$SSH_DROP_IN" "$bak"
        logger_info "Backup guardado en $bak"
    fi

    # 3. Registrar rollback por si falla
    sb_ssh_register_rollback

    # 4. Desplegar template
    deploy_template \
        "${SB_TEMPLATES_DIR}/sshd_config.tpl" \
        "$SSH_DROP_IN" \
        "SSH_PORT=${SB_SSH_PORT}" \
        "ADMIN_USER=${SB_ADMIN_USER}"

    run_cmd chmod 644 "$SSH_DROP_IN"

    # 5. Validar con sshd -t
    if ! run_cmd sshd -t; then
        logger_error "sshd -t falló, restaurando backup"
        sb_ssh_rollback_if_needed
        return 70
    fi

    # 6. Habilitar + reload (no restart — preserva la sesion actual)
    run_cmd systemctl enable ssh >/dev/null 2>&1 || true
    if ! run_cmd systemctl reload ssh; then
        logger_warn "reload ssh falló, intentando restart"
        run_cmd systemctl restart ssh || {
            logger_error "Restart ssh falló, restaurando"
            sb_ssh_rollback_if_needed
            return 70
        }
    fi

    # 7. Validacion post-aplicacion (best effort)
    logger_info "Validando acceso al puerto ${SB_SSH_PORT}..."
    sleep 2
    if command -v ssh >/dev/null 2>&1; then
        if ssh -p "${SB_SSH_PORT}" -o BatchMode=yes -o ConnectTimeout=5 -o StrictHostKeyChecking=no \
            "${SB_ADMIN_USER}@localhost" echo "OK" >/dev/null 2>&1; then
            logger_ok "Conexion SSH:${SB_SSH_PORT} verificada"
        else
            logger_warn "Conexion SSH:${SB_SSH_PORT} no respondio (probable: clave publica no copiada aun)"
            logger_warn "Recordá: copiá tu clave a /home/${SB_ADMIN_USER}/.ssh/authorized_keys"
        fi
    fi

    # Limpiar rollback porque salió bien
    SB_NEEDS_SSH_ROLLBACK="false"

    # Output
    sb_kv "Puerto SSH" "${SB_SSH_PORT}"
    sb_kv "PermitRootLogin" "no"
    sb_kv "PasswordAuthentication" "no"
    sb_kv "AllowUsers" "${SB_ADMIN_USER}"

    state_set_module_completed ssh \
        '{"ssh_port": '"${SB_SSH_PORT}"', "allow_users": ["'"${SB_ADMIN_USER}"'"]}'
    logger_ok "SSH endurecido"
}

mod_ssh_rollback() {
    sb_ssh_rollback_if_needed
}

mod_ssh_status() {
    if [[ -f "$SB_STATE_FILE" ]]; then
        state_get ssh completed_at
    fi
}
