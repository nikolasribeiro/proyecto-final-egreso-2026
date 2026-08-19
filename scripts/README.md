# `scripts/operator.sh` — Songbird Operator

> **Issue**: [#35](https://github.com/nikolasribeiro/proyecto-final-egreso-2026/issues/35)
> **Materia**: Administración de Sistemas Operativos (UTU ISBO)
> **Milestone**: Segunda Entrega Oficial
> **Estado**: implementa el DoD del issue

Script bash modular e idempotente que toma un **Debian 12 (bookworm) limpio** y
lo deja con: SSH endurecido, UFW activo, usuarios con sudoers scoped, Docker
Engine + compose plugin, cliente MariaDB, backups automáticos vía `cron.d`,
logrotate, journal persistente y el **stack del proyecto corriendo** (PHP 8.2
+ MariaDB 10.11 + Zabbix 7.0 vía `docker-compose.prod.yml`).

---

## Requisitos

- **OS**: Debian 12 (bookworm) — el script aborta en otras distros.
- **Acceso root** (o `sudo` con NOPASSWD para el operador).
- **Conectividad a internet** durante el primer `apt update` y para los `docker pull`.
- **El repo ya clonado** en `/opt/songbird-app` (o el path que se pase con `--repo-dir`).

  > El script **no clona**. Asume que el operador (o la imagen) ya lo dejó ahí.

---

## Quickstart

```bash
# (En una VM Debian 12 recién instalada)
sudo apt update && sudo apt install -y git
sudo git clone https://github.com/nikolasribeiro/proyecto-final-egreso-2026.git /opt/songbird-app
sudo chown -R $USER /opt/songbird-app

# Run completo
cd /opt/songbird-app
sudo scripts/operator.sh --all

# Smoke test final
sudo scripts/operator.sh --module verificar
# esperado: 15/15 OK, exit 0
```

Tiempo total aproximado: **8–12 minutos** en una VM con red decente.

---

## Uso por módulo

| Flag | Descripción | Idempotente |
|---|---|---|
| `--module preflight`  | Verifica OS, root, DNS, puertos libres | sí |
| `--module usuarios`   | Crea `songbird_admin`, `songbird_app`, `songbird_backup`, `songbird_zbx` | sí |
| `--module grupos`     | Crea grupos `docker`, `backup`, `songbird-ops` + sudoers scoped | sí |
| `--module ssh`        | Instala `openssh-server` y endurece con drop-in | sí (re-aplica si cambia el puerto) |
| `--module red`        | Documenta red; opcionalmente cambia hostname | sí |
| `--module firewall`   | UFW con default deny + reglas SSH/app/Zabbix | sí (reset controlado) |
| `--module docker`     | Instala Docker Engine + compose plugin desde el repo oficial | sí |
| `--module bd`         | Instala `mariadb-client` | sí |
| `--module logs`       | Logrotate + journal persistente | sí |
| `--module backups`    | `cron.d` + script de `mysqldump` + tar + rotación 7d | sí |
| `--module deploy`     | Regenera `.env` con passwords Zabbix nuevos + `docker compose up -d` | sí (skip si containers healthy) |
| `--module verificar`  | Smoke test 15 checks | re-ejecutable |
| `--all`               | Todos los anteriores en orden | sí |

---

## Variables de entorno

Equivalentes a los flags, prioridad baja.

| Var | Default | Flag |
|---|---|---|
| `SIGSM_SSH_PORT`        | `2222`                    | `--ssh-port` |
| `SIGSM_APP_PORT`        | `8000`                    | `--app-port` |
| `SIGSM_REPO_DIR`        | `/opt/songbird-app`       | `--repo-dir` |
| `SIGSM_ADMIN_USER`      | `songbird_admin`          | — |
| `SIGSM_EXPOSE_ZABBIX_WEB` | `false`                 | `--expose-zabbix-web` |
| `SIGSM_DRY_RUN`         | `false`                   | `--dry-run` |
| `SIGSM_INTERACTIVE`     | `false`                   | `--interactive` |

---

## Idempotencia

Cada módulo es re-ejecutable. Detecta "ya hecho" por:

- **State persistente** en `/var/lib/songbird-operator/state.json` (root:root 644).
- **Presencia de archivos** (`/etc/ssh/sshd_config.d/00-songbird-hardening.conf`, `/etc/cron.d/songbird-backup`, etc.).
- **Estado de servicios** (`systemctl is-active docker`, `ufw status`).
- **Versión / configuración** (si cambia `--ssh-port`, el módulo SSH re-aplica aunque esté en state).

Para forzar re-ejecución de un módulo específico:

```bash
sudo scripts/operator.sh --module ssh   # detecta el cambio y re-aplica
sudo rm /var/lib/songbird-operator/state.json   # nuclear: resetea toda la idempotencia
```

---

## Lo que hace cada módulo (resumen)

### `preflight`
- Chequea `ID=debian` + `VERSION_ID=12`.
- `EUID==0` (auto-eleva con `sudo` si no).
- `apt-get`, `systemctl`, `ss` disponibles.
- DNS resuelve `deb.debian.org`.
- Puertos `SB_SSH_PORT` y `SB_APP_PORT` libres.

### `ssh` (hardening)
- Drop-in `/etc/ssh/sshd_config.d/00-songbird-hardening.conf`:
  - `Port {{SSH_PORT}}` (default 2222)
  - `PermitRootLogin no`
  - `PasswordAuthentication no`
  - `ChallengeResponseAuthentication no`
  - `PubkeyAuthentication yes`
  - `AllowUsers {{ADMIN_USER}}`
  - `X11Forwarding no`, `AllowTcpForwarding no`, `PermitTunnel no`
  - `ClientAliveInterval 300` / `MaxAuthTries 3` / `LoginGraceTime 30`
- `sshd -t` antes de reload (rollback si falla).
- `systemctl reload ssh` (no restart — preserva la sesión actual).
- Backup automático del drop-in previo a `*.bak-<timestamp>`.

### `firewall`
- `ufw --force reset` → `default deny incoming` + `allow outgoing`.
- Permite: `SSH_PORT/tcp`, `APP_PORT/tcp`, `10050/tcp` (zabbix agent), `10051/tcp` (zabbix server).
- `--expose-zabbix-web` agrega `8080/tcp` para la UI.

### `docker`
- Repo oficial de Docker (NO `docker.io`).
- Engine + CLI + containerd + buildx + compose plugin.
- `systemctl enable --now docker`.
- Grupo `docker` con `songbird_admin` y `songbird_app`.
- `/etc/docker/daemon.json` con `log-driver: json-file` (rotación 3×10 MB).

### `bd`
- `mariadb-client` (compatible con `mariadb-dump` que usan los healthchecks).

### `backups`
- `/var/backups/songbird/` (chmod 700).
- `/usr/local/sbin/songbird-backup.sh` (template):
  - `mariadb-dump` desde contenedor `songbird_db` (con `MYSQL_ROOT_PASSWORD`).
  - `tar -czf` del repo (excluye `.git`, `.env`, `vendor`, `node_modules`).
  - `find -mtime +7 -delete` para rotación.
  - Hook `BACKUP_DEST` (rsync off-site) opcional.
- `/etc/cron.d/songbird-backup` con schedule `30 2 * * *` (02:30 AM diario).

### `logs`
- `/var/log/journal/` (journal persistente) + restart `systemd-journald`.
- `/etc/logrotate.d/songbird` (daily, 14 rotations, compress).
- Logs en `/var/log/songbird/songbird-operator.log` (chmod 640, root:adm).

### `deploy` (NO clona)
- Asume `/opt/songbird-app` ya presente con `.env.example`.
- `cp -n .env.example .env` (preserva el `.env` existente si ya está).
- **Regenera SIEMPRE** `ZABBIX_DB_PASSWORD` y `ZABBIX_DB_ROOT_PASSWORD` con `openssl rand -base64 32`.
  Los defaults literales `cambiar_en_produccion` rompen los healthchecks de Zabbix.
- `APP_ENVIRONMENT=production` (cierra el seeder HTTP `/seed`).
- `APP_PORT=${SB_APP_PORT}`.
- `chmod 600 .env`.
- `docker compose -f docker-compose.prod.yml --env-file .env up -d`.
- Espera hasta 180s para que todos los contenedores estén healthy.
- Guarda credenciales generadas en `/opt/songbird-operator/credentials.env` (chmod 600).

### `verificar` (smoke test, 15 checks)

| # | Check | Esperado |
|---|---|---|
| 1  | `sshd -T \| grep permitrootlogin` | `no` |
| 2  | `ss -ltn sport = :${SSH_PORT}` | LISTEN |
| 3  | `ufw status` | active |
| 4  | `ufw status` contiene SSH+APP rules | match |
| 5  | `systemctl is-active docker` | active |
| 6  | `id songbird_app` | miembro de `docker` |
| 7  | `docker compose version` | exit 0 |
| 8  | `docker compose ps` (Health) | todos healthy |
| 9  | `curl http://localhost:${APP_PORT}/` | 2xx/3xx |
| 10 | `curl http://localhost:${APP_PORT}/zabbix/` | 2xx/3xx |
| 11 | `docker exec songbird_db mariadb-admin ping` | OK |
| 12 | `docker exec zabbix-db-prod mysqladmin ping` | OK |
| 13 | `/etc/cron.d/songbird-backup` existe | OK |
| 14 | `logrotate -d` sin errores | OK |
| 15 | `stat -c '%a' .env` | `600` |

**Exit codes**: `0` = todo OK · `1..N` = N checks fallidos · `64` = config inválida · `70` = error interno.

---

## Tareas post-deploy (manuales del operador)

Después de que `verificar` pase 15/15, hacé lo siguiente a mano:

1. **Copiar tu clave pública SSH al host**:
   ```bash
   ssh-copy-id -p 2222 songbird_admin@<host>
   ```
   (La cuenta `songbird_admin` tiene `passwd -l`; solo se puede entrar por key.)

2. **Cambiar el password `Admin/zabbix` por defecto** en la UI de Zabbix:
   `http://<host>:8000/zabbix/` → usuario `Admin`, password `zabbix` → cambiar en el primer login.

3. **Vincular los 3 hosts sidecar de Zabbix** (esperar ~60s después del primer arranque):
   - Configuration → Hosts → Create host
   - `songbird_proxy`, `songbird_web`, `songbird_db`
   - Template: `Linux by Zabbix agent 2`
   - IP: IP del contenedor (obtenida con `docker inspect <container> | jq '.[0].NetworkSettings.IPAddress'`)

4. **Validar el backup**:
   ```bash
   sudo /usr/local/sbin/songbird-backup.sh
   ls -lh /var/backups/songbird/
   ```
   Debería crear `db-<timestamp>.sql.gz` y `repo-<timestamp>.tar.gz`.

5. **Cerrar sesión de la VM de bootstrap** y reconectarte por el puerto nuevo:
   ```bash
   ssh -p 2222 songbird_admin@<host>
   ```

---

## Limitaciones (out of scope)

- **No instala stack ELK / Loki / Grafana** para logs centralizados.
- **No automatiza la vinculación de hosts Zabbix** vía API (queda como tarea manual en la UI).
- **No configura TLS** (no hay certificados en el repo todavía).
- **No incluye backups off-site** (rsync remoto, S3) — el script prepara el hook `BACKUP_DEST` pero no configura destino.
- **No automatiza la rotación de credenciales de Zabbix** post-deploy.
- **No instala un servidor MySQL en el host** — todo se corre dentro de Docker.
- **Cluster / alta disponibilidad** — asume single-node.

---

## Troubleshooting

### "PermitRootLogin no" o "PasswordAuthentication no" bloqueó el acceso
Restaurá el drop-in:
```bash
ls /etc/ssh/sshd_config.d/00-songbird-hardening.conf.bak-*
sudo mv /etc/ssh/sshd_config.d/00-songbird-hardening.conf.bak-<ts> /etc/ssh/sshd_config.d/00-songbird-hardening.conf
sudo sshd -t && sudo systemctl reload ssh
```

### `deploy` falla con "volumen mariadb_data preexistente"
```bash
# Verificar si tenés schema viejo
docker volume inspect mariadb_data
# Si querés recrear todo desde cero (DESTRUCTIVO):
sudo scripts/operator.sh --all --interactive --reset-volumes
```

### Healthcheck de Zabbix no pasa
```bash
docker logs zabbix-server-prod 2>&1 | tail -50
docker logs zabbix-db-prod 2>&1 | tail -20
# Común: los defaults 'cambiar_en_produccion' en .env. El script los regenera, pero
# si editaste .env a mano, volver a correr:
sudo scripts/operator.sh --module deploy
```

### DNS no resuelve `deb.debian.org`
`preflight` aborta. Verificá:
```bash
cat /etc/resolv.conf
ping 8.8.8.8
# Si ping funciona pero DNS no, agregar nameserver manual:
echo "nameserver 8.8.8.8" | sudo tee -a /etc/resolv.conf
```

### Puertos 2222 / 8000 ocupados
`preflight` avisa pero no aborta. Matá al dueño o cambiá el puerto:
```bash
sudo ss -ltnp | grep -E ':(2222|8000)\b'
sudo scripts/operator.sh --all --ssh-port 22022 --app-port 80
```

### `logrotate` reporta warnings
Generalmente OK. Para debug:
```bash
sudo logrotate -d /etc/logrotate.d/songbird
```

### Logs del operador
```bash
sudo tail -f /var/log/songbird-operator.log
sudo journalctl -u docker
```

---

## Seguridad

- **Nunca commitear** `.env`, `credentials.env`, `state.json`, ni archivos `.bak` generados por el script. Ya están en `.gitignore` (excepto los `.bak` — agregados en este PR).
- El archivo `/opt/songbird-operator/credentials.env` (chmod 600) contiene los passwords generados — accesible solo por root.
- El `sshd_config.d/00-songbird-hardening.conf` rechaza passwords: solo key-based.

---

## Archivos generados (resumen)

```
/etc/ssh/sshd_config.d/00-songbird-hardening.conf         # SSH hardening
/etc/ssh/sshd_config.d/00-songbird-hardening.conf.bak-*   # backups rotativos
/etc/sudoers.d/songbird_admin                             # sudoers scoped
/etc/cron.d/songbird-backup                               # cron de backups
/etc/logrotate.d/songbird                                 # logrotate
/etc/docker/daemon.json                                   # docker log driver
/var/backups/songbird/                                    # dumps + snapshots (chmod 700)
/var/log/songbird/                                        # logs de la app
/var/log/songbird-operator.log                            # log del operador
/var/log/journal/                                         # journal persistente
/var/lib/songbird-operator/state.json                     # estado de idempotencia
/opt/songbird-operator/credentials.env                    # passwords generados (chmod 600)
```

---

## Relación con `.docs/master-plan.md`

El script **NO** modifica el plan maestro. Si en el futuro querés extender la
sección "Operación y despliegue" del plan maestro, referenciá este script como
la herramienta canónica de bootstrap.
