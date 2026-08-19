#!/usr/bin/env bash
# scripts/lib/docker.sh
# Instalacion de Docker Engine + Compose plugin desde el repo oficial.
# Modulo: docker

set -euo pipefail
IFS=$'\n\t'

mod_docker_run() {
    logger_section "Módulo docker"

    if module_skip_if_done docker \
        'command -v docker >/dev/null && docker compose version >/dev/null 2>&1 && systemctl is-active docker >/dev/null 2>&1'; then
        return 0
    fi

    # 1. Prereqs
    logger_info "Instalando prerequisitos"
    run_cmd apt-get update -qq
    run_cmd apt-get install -y -qq ca-certificates curl gnupg

    # 2. Keyring
    run_cmd install -m 0755 -d /etc/apt/keyrings
    if [[ ! -f /etc/apt/keyrings/docker.gpg ]]; then
        run_cmd curl -fsSL https://download.docker.com/linux/debian/gpg \
            -o /etc/apt/keyrings/docker.gpg
        run_cmd chmod a+r /etc/apt/keyrings/docker.gpg
    fi

    # 3. Sources.list
    if [[ ! -f /etc/apt/sources.list.d/docker.list ]]; then
        local arch
        arch="$(dpkg --print-architecture)"
        local codename
        codename="$(. /etc/os-release && echo "$VERSION_CODENAME")"
        run_cmd tee /etc/apt/sources.list.d/docker.list >/dev/null <<EOF
deb [arch=${arch} signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/debian ${codename} stable
EOF
    fi

    # 4. Instalar paquetes
    run_cmd apt-get update -qq
    run_cmd apt-get install -y -qq \
        docker-ce \
        docker-ce-cli \
        containerd.io \
        docker-buildx-plugin \
        docker-compose-plugin

    # 5. Habilitar y arrancar
    run_cmd systemctl enable --now docker

    # 6. Grupo docker para usuarios
    if ! getent group docker >/dev/null 2>&1; then
        run_cmd groupadd docker
    fi
    run_cmd usermod -aG docker "$SB_ADMIN_USER" || true
    run_cmd usermod -aG docker songbird_app || true

    # 7. Daemon con log rotation
    if [[ ! -f /etc/docker/daemon.json ]] || ! grep -q "json-file" /etc/docker/daemon.json 2>/dev/null; then
        run_cmd tee /etc/docker/daemon.json >/dev/null <<'EOF'
{
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "10m",
    "max-file": "3"
  }
}
EOF
        run_cmd systemctl restart docker
    fi

    # 8. Verificar
    local docker_version
    docker_version="$(docker --version 2>/dev/null || echo 'no disponible')"
    local compose_version
    compose_version="$(docker compose version 2>/dev/null || echo 'no disponible')"

    sb_kv "docker" "$docker_version"
    sb_kv "compose" "$compose_version"
    sb_kv "daemon" "active (log rotate 3x10m)"

    state_set_module_completed docker \
        '{"docker_version": "'"${docker_version}"'", "compose_version": "'"${compose_version}"'"}'
    logger_ok "Docker instalado y configurado"
}

mod_docker_status() {
    docker --version 2>/dev/null
    docker compose version 2>/dev/null
}
