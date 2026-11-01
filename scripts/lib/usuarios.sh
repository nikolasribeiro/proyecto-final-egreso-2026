#!/usr/bin/env bash
# scripts/lib/usuarios.sh
# Creacion de usuarios del sistema.
# Modulo: usuarios

set -euo pipefail
IFS=$'\n\t'

mod_usuarios_run() {
    logger_section "Módulo usuarios"

    # Home del admin (puede no existir todavia en la primera corrida).
    local admin_home
    admin_home="$(getent passwd "$SB_ADMIN_USER" 2>/dev/null | cut -d: -f6)"
    [[ -z "$admin_home" ]] && admin_home="/home/${SB_ADMIN_USER}"

    # La precondicion incluye la llave: si el usuario existe pero la llave
    # falta (p.ej. se borro el authorized_keys), el modulo se re-aplica.
    local precond
    precond="id ${SB_ADMIN_USER} && id songbird_app && id songbird_backup && id songbird_zbx"
    precond+=" && [[ -s '${admin_home}/.ssh/authorized_keys' ]]"
    if module_skip_if_done usuarios "$precond"; then
        return 0
    fi

    # 1. songbird_admin — usuario para el operador, con home y bash
    if id "$SB_ADMIN_USER" >/dev/null 2>&1; then
        logger_ok "Usuario $SB_ADMIN_USER ya existe"
    else
        run_cmd useradd -m -s /bin/bash -c "Songbird Operator" "$SB_ADMIN_USER"
        logger_ok "Usuario $SB_ADMIN_USER creado"
    fi

    # 2. songbird_app — usuario del sistema, sin login, miembro de docker
    if id songbird_app >/dev/null 2>&1; then
        logger_ok "Usuario songbird_app ya existe"
    else
        run_cmd useradd -r -s /usr/sbin/nologin -M -c "Songbird App Runtime" songbird_app
        logger_ok "Usuario songbird_app creado"
    fi

    # 3. songbird_backup — sin login, miembro de backup
    if id songbird_backup >/dev/null 2>&1; then
        logger_ok "Usuario songbird_backup ya existe"
    else
        run_cmd useradd -r -s /usr/sbin/nologin -M -c "Songbird Backup Operator" songbird_backup
        logger_ok "Usuario songbird_backup creado"
    fi

    # 4. songbird_zbx — sin login, reserva para instalación Zabbix host-side
    if id songbird_zbx >/dev/null 2>&1; then
        logger_ok "Usuario songbird_zbx ya existe"
    else
        run_cmd useradd -r -s /usr/sbin/nologin -M -c "Songbird Zabbix Host User" songbird_zbx
        logger_ok "Usuario songbird_zbx creado"
    fi

    # 5. .ssh para el admin (key-only auth)
    run_cmd install -d -m 700 -o "$SB_ADMIN_USER" -g "$SB_ADMIN_USER" "$admin_home/.ssh"
    if [[ ! -f "$admin_home/.ssh/authorized_keys" ]]; then
        run_cmd install -m 600 -o "$SB_ADMIN_USER" -g "$SB_ADMIN_USER" /dev/null "$admin_home/.ssh/authorized_keys"
    fi

    # 5.b Llave SSH del admin: se instala la del operador si existe, o se
    #     genera un par ed25519 en el servidor. Asi el modulo `ssh` puede
    #     validar la conexion de verdad.
    logger_section "Llave SSH de ${SB_ADMIN_USER}"
    if sb_ssh_key_setup "$admin_home"; then
        if [[ -f "${admin_home}/.ssh/id_ed25519" ]]; then
            logger_warn "La llave privada quedó en el servidor. Traela y borrala (ver el resumen final)."
        else
            logger_ok "Llave pública lista: la privada nunca sale de tu máquina"
        fi
    else
        logger_warn "No se pudo configurar la llave SSH: copiala a mano en ${admin_home}/.ssh/authorized_keys"
    fi

    # 6. Lockear password del admin (solo key-based via SSH)
    run_cmd passwd -l "$SB_ADMIN_USER" >/dev/null 2>&1 || true

    # Tabla resumen
    printf '\n' >&2
    sb_kv "usuario" "uid | shell | grupos | home"
    for u in "$SB_ADMIN_USER" songbird_app songbird_backup songbird_zbx; do
        if id "$u" >/dev/null 2>&1; then
            local info
            info="$(getent passwd "$u")"
            local uid shell home
            uid="$(echo "$info" | cut -d: -f3)"
            shell="$(echo "$info" | cut -d: -f7)"
            home="$(echo "$info" | cut -d: -f6)"
            local groups
            groups="$(id -nG "$u" | tr ' ' ',')"
            sb_kv "  $u" "$uid | $shell | $groups | $home"
        fi
    done

    state_set_module_completed usuarios '{"admin_user": "'"${SB_ADMIN_USER}"'"}'
    logger_ok "Usuarios creados/verificados"
}

mod_usuarios_status() {
    if [[ -f "$SB_STATE_FILE" ]]; then
        state_get usuarios completed_at
    fi
}
