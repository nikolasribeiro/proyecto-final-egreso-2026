#!/usr/bin/env bash
# scripts/lib/red.sh
# Documentacion y configuracion minima de red.
# Modulo: red

set -euo pipefail
IFS=$'\n\t'

mod_red_run() {
    logger_section "Módulo red"

    # Cambiar hostname si se paso --hostname
    local new_hostname="${RED_NEW_HOSTNAME:-}"
    if [[ -n "$new_hostname" ]]; then
        if [[ "$(hostname)" != "$new_hostname" ]]; then
            run_cmd hostnamectl set-hostname "$new_hostname"
            logger_ok "Hostname actualizado a $new_hostname"
            if grep -q "^127.0.1.1" /etc/hosts 2>/dev/null; then
                run_cmd sed -i.bak "s|^127.0.1.1.*|127.0.1.1\t${new_hostname}|" /etc/hosts
            else
                printf '127.0.1.1\t%s\n' "$new_hostname" | run_cmd tee -a /etc/hosts >/dev/null
            fi
        else
            logger_ok "Hostname ya es $new_hostname"
        fi
    else
        logger_info "Hostname no modificado (usar --hostname para cambiar)"
    fi

    # Documentar (no modificar agresivamente)
    sb_kv "Hostname" "$(hostname)"
    sb_kv "FQDN" "$(hostname -f 2>/dev/null || echo 'n/a')"

    local ipv4
    ipv4="$(ip -4 addr show 2>/dev/null | awk '/inet /{print $2}' | tr '\n' ',' | sed 's/,$//')"
    sb_kv "IPv4" "${ipv4:-n/a}"

    local gw
    gw="$(ip route 2>/dev/null | awk '/default/ {print $3}' | head -n1)"
    sb_kv "Gateway" "${gw:-n/a}"

    local dns
    dns="$(awk '/^nameserver/ {print $2}' /etc/resolv.conf 2>/dev/null | tr '\n' ',' | sed 's/,$//')"
    sb_kv "Nameservers" "${dns:-n/a}"

    local listening
    listening="$(ss -ltn 2>/dev/null | awk 'NR>1 {print $4}' | grep -oE ':[0-9]+$' | sort -u | tr '\n' ',' | sed 's/,$//')"
    sb_kv "Listening ports" "${listening:-ninguno}"

    # No se persiste estado: el modulo es informativo / opcional.
    logger_ok "Red documentada"
}

mod_red_status() {
    hostname
}
