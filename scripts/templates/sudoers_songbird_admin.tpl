# /etc/sudoers.d/songbird_admin
# Gestionado por songbird-operator (issue #35). Validador recomendado: visudo -c -f <este archivo>
# Permite al usuario admin operar la app sin password (NOPASSWD scoped).

Defaults env_keep += "SB_VERSION SB_NAME SB_REPO_DIR SB_SSH_PORT SB_APP_PORT"

# Operacion del stack (docker compose, restart, logs)
{{ADMIN_USER}} ALL=(root) NOPASSWD: /usr/bin/docker compose -f /opt/songbird-app/docker-compose.prod.yml *
{{ADMIN_USER}} ALL=(root) NOPASSWD: /usr/bin/docker compose -f /opt/songbird-app/docker-compose.dev.yml *
{{ADMIN_USER}} ALL=(root) NOPASSWD: /usr/bin/systemctl * docker
{{ADMIN_USER}} ALL=(root) NOPASSWD: /usr/bin/systemctl * songbird-backup
{{ADMIN_USER}} ALL=(root) NOPASSWD: /usr/local/sbin/songbird-backup.sh

# Operador (solo este binario, para tareas de bootstrap)
{{ADMIN_USER}} ALL=(root) NOPASSWD: /opt/songbird-operator/scripts/operator.sh *

# Backups y logs
{{ADMIN_USER}} ALL=(root) NOPASSWD: /usr/bin/rsync *
{{ADMIN_USER}} ALL=(root) NOPASSWD: /usr/bin/find /var/backups/songbird *
{{ADMIN_USER}} ALL=(root) NOPASSWD: /usr/bin/tail -F /var/log/songbird-operator.log
