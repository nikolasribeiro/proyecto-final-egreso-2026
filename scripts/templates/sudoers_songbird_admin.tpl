# /etc/sudoers.d/songbird_admin
# Gestionado por songbird-operator (issue #35). Validador recomendado: visudo -c -f <este archivo>
# Permite al usuario admin operar la app sin password (NOPASSWD scoped).
#
# NOTA: el stack se opera con el compose file UNICO `docker-compose.yml`
# (mismo archivo para desarrollo y produccion). Las reglas que apuntan a
# `docker-compose.prod.yml` / `docker-compose.dev.yml` quedaron huerfanas cuando
# esos archivos se eliminaron del repo.

Defaults env_keep += "SB_VERSION SB_NAME SB_REPO_DIR SB_SSH_PORT SB_APP_PORT"

# Operacion del stack (docker compose, restart, logs)
{{ADMIN_USER}} ALL=(root) NOPASSWD: /usr/bin/docker compose -f {{REPO_DIR}}/docker-compose.yml *
{{ADMIN_USER}} ALL=(root) NOPASSWD: /usr/bin/docker compose --env-file {{REPO_DIR}}/.env *
{{ADMIN_USER}} ALL=(root) NOPASSWD: /usr/bin/systemctl * docker
{{ADMIN_USER}} ALL=(root) NOPASSWD: /usr/bin/systemctl * songbird-backup
{{ADMIN_USER}} ALL=(root) NOPASSWD: /usr/local/sbin/songbird-backup.sh

# Operador (solo este binario, para tareas de bootstrap)
{{ADMIN_USER}} ALL=(root) NOPASSWD: {{REPO_DIR}}/scripts/operator.sh *

# Backups y logs
{{ADMIN_USER}} ALL=(root) NOPASSWD: /usr/bin/rsync *
{{ADMIN_USER}} ALL=(root) NOPASSWD: /usr/bin/find /var/backups/songbird *
{{ADMIN_USER}} ALL=(root) NOPASSWD: /usr/bin/tail -F /var/log/songbird-operator.log
