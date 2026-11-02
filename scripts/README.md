# `scripts/operator.sh` — Songbird Operator

> **Issue**: [#35](https://github.com/nikolasribeiro/proyecto-final-egreso-2026/issues/35)
> **Materia**: Administración de Sistemas Operativos (UTU ISBO)
> **Milestone**: Segunda Entrega Oficial

Script bash modular e idempotente que toma un **Debian 13 (trixie) limpio** y
lo deja con: SSH endurecido, UFW activo, usuarios con sudoers scoped, Docker
Engine + compose plugin, cliente MariaDB, backups automáticos vía `cron.d`,
logrotate, journal persistente y el **stack del proyecto corriendo** (PHP 8.2 + MariaDB 10.11 **master/slave** + Zabbix 7.0) vía `docker-compose.yml`.

---

## Requisitos

- **OS**: Debian 13 (trixie) — el objetivo de despliegue. Debian 12 (bookworm)
  también está soportado; otras distros abortan.
- **Acceso root** (o `sudo` para el operador).
- **Conectividad a internet** durante el primer `apt update` y para los `docker pull`.
- **Un checkout del repo** (tú lo clonás; el operador no clona).

---

## Quickstart

```bash
# ── PASO 1 (solo la primera vez): como root ───────────────────────────
# El operador necesita root, y para llegar a root hace falta `sudo`.
# Si `sudo` no está instalado, el propio operador no puede instalarlo
# (instalar paquetes requiere root). Por eso la primera ejecución es `su -`:
su -
apt-get update && apt-get install -y sudo git
exit

# ── PASO 2: con sudo ya disponible ────────────────────────────────────
# 1. Clonás el proyecto (el operador NO lo hace)
git clone https://github.com/nikolasribeiro/proyecto-final-egreso-2026.git ~/proyecto
cd ~/proyecto

# 2. Cargás tus variables
cp .env.example .env
nano .env

# 3. Corrés el operador desde el checkout
sudo scripts/operator.sh --all

# 4. Smoke test final
sudo scripts/operator.sh --module verificar
```

> **¿Por qué el primer paso es como root?** El operador configura el host
> (`systemctl`, `ufw`, `apt`, `docker`), así que necesita privilegios de root.
> Para obtenerlos usa `sudo`… y si `sudo` no está instalado no hay forma de
> llegar a root, ni de instalar `sudo` a mano. La primera ejecución se hace
> entonces con `su -`. A partir de ahí el módulo `deps` deja `sudo`
> instalado y todas las ejecuciones siguientes son `sudo scripts/operator.sh --all`.
>
> Si ya tenés `sudo` (casi todos los escritorios), el paso 1 se saltea.

> **El operador trabaja in-place**: usa el repo donde vive el script.
> Si lo clonás en `~/proyecto`, despliega desde `~/proyecto`.
> `/opt/songbird-app` es la **convención documentada** de despliegue
> (recomendado en producción), no una ruta que el script te imponga:
> si clonás ahí, el operador opera ahí sin más.

Tiempo total aproximado: **8–12 minutos** en una VM con red decente.

---

## Orden de los módulos

| #   | Módulo      | Qué hace                                                                 | Idempotente                        |
| --- | ----------- | ------------------------------------------------------------------------ | ---------------------------------- |
| 1   | `deps`      | Instala la toolchain base del host                                       | sí                                 |
| 2   | `preflight` | Valida OS, root, DNS, puertos, binarios                                  | sí                                 |
| 3   | `repo`      | Verifica el proyecto (y sincroniza si usás `--repo-dir`)                 | sí                                 |
| 4   | `usuarios`  | Crea `songbird_admin`, `songbird_app`, `songbird_backup`, `songbird_zbx` | sí                                 |
| 5   | `grupos`    | Crea grupos `docker`, `backup`, `songbird-ops` + sudoers scoped          | sí                                 |
| 6   | `ssh`       | Instala `openssh-server` y endurece con drop-in                          | sí (re-aplica si cambia el puerto) |
| 7   | `red`       | Documenta red; opcionalmente cambia hostname                             | sí                                 |
| 8   | `firewall`  | UFW con default deny + reglas SSH/app/Zabbix                             | sí (reset controlado)              |
| 9   | `docker`    | Instala Docker Engine + compose plugin desde el repo oficial             | sí                                 |
| 10  | `bd`        | Instala `mariadb-client`                                                 | sí                                 |
| 11  | `logs`      | Logrotate + journal persistente                                          | sí                                 |
| 12  | `backups`   | `cron.d` + script de `mysqldump` + tar + rotación 7d                     | sí                                 |
| 13  | `deploy`    | Completa el `.env` (solo huecos) + `docker compose up -d`                | sí (skip si healthy)               |
| 14  | `verificar` | Smoke test de 21 checks                                                  | re-ejecutable                      |

### Por qué `deps` va primero

En una Debian 13 mínima (imagen `genericcloud`) **no vienen** `jq`, `git`,
`curl`, `openssl`, `rsync`, `logrotate` ni `sudo`. El operador los necesita:

| Paquete     | Para qué                                          |
| ----------- | ------------------------------------------------- |
| `jq`        | Persistencia del estado (idempotencia)            |
| `git`       | Clonar el repo si no hay checkout local           |
| `curl`      | Healthchecks HTTP del smoke test                  |
| `openssl`   | Generación de passwords en `deploy`               |
| `rsync`     | Sincronización del repo (módulo `repo`) y backups |
| `logrotate` | Módulo `logs`                                     |
| `iproute2`  | `ss`, usado por `preflight` y `verificar`         |
| `sudo`      | Permite re-elevar el operador en el futuro        |

Sin el módulo `deps`, el operador abortaba en el primer módulo que tocaba `jq`.

---

## Uso por módulo

```bash
sudo scripts/operator.sh --module <nombre> [opciones]
```

| Flag                                  | Descripción                                                                                           |
| ------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| `--all`                               | Ejecuta los 14 módulos en orden                                                                       |
| `--module <nombre>`                   | Ejecuta solo un módulo                                                                                |
| `--uninstall`                         | Reversa los módulos (orden inverso, requiere `--interactive`)                                         |
| `--hostname <fqdn>`                   | Cambia el hostname del host                                                                           |
| `--ssh-port <N>`                      | Puerto SSH (default: 2222)                                                                            |
| `--app-port <N>`                      | **Fuerza** `APP_PORT` en el `.env`. Si no lo pasás, tu `APP_PORT` del `.env` se respeta               |
| `--environment <nombre>`              | **Fuerza** `APP_ENVIRONMENT` (`development` / `production`). Si no lo pasás, se respeta el del `.env` |
| `--repo-dir <path>`                   | Despliega en otro directorio (sincroniza el checkout, **incluido tu `.env`**)                         |
| `--ssh-key <archivo.pub>`             | Llave pública a instalar para el admin (default: usa la tuya si existe, o genera una)                 |
| `--allow-user <usuario>`              | Usuario extra autorizado por SSH (repetible). Sin esto, `AllowUsers` deja entrar **solo** al admin    |
| `--expose-zabbix-web`                 | Abre el puerto 8080 en UFW para la UI de Zabbix                                                       |
| `--reset-volumes`                     | Borra los volúmenes de datos (DESTRUCTIVO, requiere `--interactive`)                                  |
| `--interactive` / `--non-interactive` | Prompts de confirmación (default: no interactivo)                                                     |
| `--dry-run`                           | Muestra los comandos sin ejecutarlos                                                                  |
| `--help` / `--version`                | Ayuda / versión                                                                                       |

> **El `.env` es tuyo.** El operador solo rellena lo que está vacío y solo
> pisa `APP_PORT` / `APP_ENVIRONMENT` si los pedís por flag.

### Variables de entorno

Equivalentes a los flags, con prioridad baja.

| Var                       | Default                   | Flag                  |
| ------------------------- | ------------------------- | --------------------- |
| `SIGSM_SSH_PORT`          | `2222`                    | `--ssh-port`          |
| `SIGSM_APP_PORT`          | valor del `.env` o `8000` | `--app-port`          |
| `SIGSM_REPO_DIR`          | el checkout del script    | `--repo-dir`          |
| `SIGSM_ADMIN_USER`        | `songbird_admin`          | —                     |
| `SIGSM_EXPOSE_ZABBIX_WEB` | `false`                   | `--expose-zabbix-web` |
| `SIGSM_DRY_RUN`           | `false`                   | `--dry-run`           |
| `SIGSM_INTERACTIVE`       | `false`                   | `--interactive`       |

---

## Idempotencia

Cada módulo es re-ejecutable. Detecta "ya hecho" por:

- **State persistente** en `/var/lib/songbird-operator/state.json` (root:root 644).
- **Presencia de archivos** (`/etc/ssh/sshd_config.d/00-songbird-hardening.conf`, `/etc/cron.d/songbird-backup`, etc.).
- **Estado de servicios** (`systemctl is-active docker`, `ufw status`).
- **Estado real del stack**: `deploy` consulta `docker compose ps --format json`
  y solo redeploya si algún servicio no está `healthy` (o es un one-shot que
  no terminó en 0).

Para forzar re-ejecución de un módulo específico:

```bash
sudo scripts/operator.sh --module ssh   # detecta el cambio y re-aplica
sudo rm /var/lib/songbird-operator/state.json   # nuclear: resetea toda la idempotencia
```

---

## Lo que hace cada módulo (resumen)

### `deps`

- Instala la toolchain base (`SB_BASE_PACKAGES` en `scripts/lib/deps.sh`).
- Es idempotente: si los paquetes ya están, no toca apt.
- Verifica post-install que los binarios críticos estén en el PATH.

### `preflight`

- Chequea `ID=debian` + `VERSION_ID` (13/trixie u objetivo; 12/bookworm soportado).
- `EUID==0` (auto-eleva con `sudo` si no).
- `apt-get`, `systemctl`, `ss` disponibles.
- DNS resuelve `deb.debian.org`.
- Puertos `SB_SSH_PORT` y `SB_APP_PORT` libres.
- Toolchain base presente (avisa si falta el módulo `deps`).
- Detecta el checkout origen y avisa si no parece el proyecto.

### `repo`

Verifica el proyecto del despliegue. **No clona ni copia nada.**

1. Detecta el checkout por la **ubicación de los scripts** (`scripts/lib/../..`),
   no por el CWD.
2. Verifica que estén `docker-compose.yml` y `.env.example`.
3. Si falta el `.env`, lo crea desde `.env.example` (con permisos 600).
   Si existe, **no lo toca**.
4. Si pasaste `--repo-dir <otro path>`, sincroniza el checkout hacia ese
   destino **incluyendo tu `.env`** (para no perder tus variables).

### `usuarios`
- Crea `songbird_admin`, `songbird_app`, `songbird_backup`, `songbird_zbx`.
- Crea `/home/songbird_admin/.ssh/` (700) y `authorized_keys` (600).
- **Configura la llave SSH del administrador** (ver abajo).

### Llaves SSH del administrador

El módulo `usuarios` deja el acceso por SSH **listo para usar**. La llave
pública queda en `/home/songbird_admin/.ssh/authorized_keys` y hay 3 formas de
resolverla, en este orden:

| Prioridad | Origen | Qué implica |
|---|---|---|
| 1 | `--ssh-key <archivo.pub>` | Instalás la llave que quieras explícitamente. |
| 2 | La del usuario que invocó el operador (`$SUDO_USER`) | Se detecta sola (`~/.ssh/id_ed25519.pub`, `id_ecdsa.pub` o `id_rsa.pub`). **La llave privada nunca toca el servidor.** |
| 3 | Par ed25519 generado en el servidor | Si no hay ninguna previa, se genera `id_ed25519` en `/home/songbird_admin/.ssh/`. |

En el caso 3 la **llave privada queda en el servidor**.

> **Ojo con el círculo**: si `AllowUsers` deja entrar solo a `songbird_admin` y
> todavía no tenés la llave privada en tu máquina, no podés usar `scp` para
> traerla (necesitás la llave para autenticarte). La salida es la **consola del
> servidor** (Proxmox, Cockpit, consola del proveedor):
>
> ```bash
> sudo cat /home/songbird_admin/.ssh/id_ed25519     # copiala a tu maquina
> chmod 600 ~/.ssh/songbird_admin_ed25519
> ssh -i ~/.ssh/songbird_admin_ed25519 -p 2222 songbird_admin@<host>
> sudo rm -f /home/songbird_admin/.ssh/id_ed25519    # borrala del servidor
> ```
>
> Para no quedar afuera en el futuro, usá `--allow-user <tu_usuario>`: ese
> usuario entra por SSH aunque la llave del admin todavía no la tengas.

Con la llave configurada, el módulo `ssh` **valida la conexión de verdad**:
se conecta a `localhost:${SSH_PORT}` **como `songbird_admin`** y usando su
llave. Antes la prueba corría como `root`, que buscaba la clave en
`/root/.ssh/` y por eso siempre terminaba en "no respondió".

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
- Permite: `SSH_PORT/tcp`, `APP_PORT/tcp`, `10050/tcp` (zabbix agent),
  `10051/tcp` (zabbix server).
- **Permite además el puerto de la sesión SSH actual** (`$SSH_CONNECTION`):
  sin esto, el `default deny` cortaba la sesión del propio operador en
  mitad del deploy.
- `--expose-zabbix-web` agrega `8080/tcp` para la UI.

### `docker`

- Repo oficial de Docker (NO `docker.io`).
- Engine + CLI + containerd + buildx + compose plugin.
- `systemctl enable --now docker`.
- Grupo `docker` con `songbird_admin` y `songbird_app`.
- `/etc/docker/daemon.json` con `log-driver: json-file` (rotación 3×10 MB).
- **Verifica que `docker compose` sea ≥ 2.20** (necesario para
  `pull --ignore-buildable`).

### `bd`

- `mariadb-client` (compatible con `mariadb-dump` que usan los healthchecks).

### `backups`

- `/var/backups/songbird/` (chmod 700).
- `/usr/local/sbin/songbird-backup.sh` (template):
  - `mariadb-dump` del contenedor **master** (`songbird_db_master`).
  - Compara el número de tablas entre master y slave (consistencia).
  - `tar -czf` del repo (excluye `.git`, `.env`, `vendor`, `node_modules`).
  - `find -mtime +7 -delete` para rotación.
  - Hook `BACKUP_DEST` (rsync off-site) opcional.
- `/etc/cron.d/songbird-backup` con schedule `30 2 * * *` (02:30 AM diario).

### `logs`

- `/var/log/journal/` (journal persistente) + restart `systemd-journald`.
- `/etc/logrotate.d/songbird` (daily, 14 rotations, compress).
- Logs en `/var/log/songbird/songbird-operator.log` (chmod 640, root:adm).

### `deploy`

- Usa el compose file **único** `docker-compose.yml` (dev y producción).
- **No sobreescribe tu `.env`.** Solo rellena lo que está vacío:
  - `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_REPLICATION_USER`, `PMA_USER`,
    `PMA_PASSWORD`.
  - `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD`, `MYSQL_REPLICATION_PASSWORD`,
    `ZABBIX_DB_PASSWORD`, `ZABBIX_DB_ROOT_PASSWORD` (los secretos **NO** se
    regeneran si ya existen; regenerarlos rompía el stack ya desplegado,
    porque la BD interna de Zabbix conservaba el password viejo).
  - Los hosts del split read/write: `PMA_HOST`, `DB_HOST`, `DB_SLAVE_HOST`,
    `DB_READ_REPLICA_ENABLED`, `DB_REPLICA_WAIT_AFTER_WRITE`,
    `DB_REPLICA_WAIT_TIMEOUT_MS`.
- **Solo fuerza** `APP_ENVIRONMENT` y `APP_PORT` si los pediste por flag
  (`--environment`, `--app-port`). Un `--app-port` que no diste no pisa el
  `APP_PORT` que configuraste a mano.
- `chmod 600 .env`.
- `docker compose build` → `pull --ignore-buildable` → `up -d --remove-orphans`.
- Espera hasta 300s a que todos los contenedores estén `healthy`. Los
  contenedores **one-shot** (p. ej. el inicializador de replicación) cuentan
  como OK si terminan con exit code 0.
- Guarda las credenciales en `/opt/songbird-operator/credentials.env` (chmod 600).

### `verificar` (smoke test, 23 checks)

| #   | Check                                                    | Esperado                          |
| --- | -------------------------------------------------------- | --------------------------------- |
| 1   | `sshd -T \| grep permitrootlogin`                        | `no`                              |
| 2   | `ss -ltn sport = :${SSH_PORT}`                           | LISTEN                            |
| 3   | `ufw status`                                             | active                            |
| 4   | `ufw status` contiene SSH+APP rules                      | match                             |
| 5   | `systemctl is-active docker`                             | active                            |
| 6   | `id songbird_app`                                        | miembro de `docker`               |
| 7   | `docker compose version`                                 | exit 0                            |
| 8   | `docker compose ps`                                      | todos healthy (o one-shot exit 0) |
| 9   | `curl http://localhost:${APP_PORT}/`                     | 2xx/3xx                           |
| 10  | `curl http://localhost:${APP_PORT}/zabbix/`              | 2xx/3xx                           |
| 11  | ping del contenedor **master**                           | OK                                |
| 12  | ping de la BD de Zabbix                                  | OK                                |
| 13  | `/etc/cron.d/songbird-backup` existe                     | OK                                |
| 14  | `logrotate -d` sin errores                               | OK                                |
| 15  | `stat -c '%a' .env`                                      | `600`                             |
| 16  | Master: `@@log_bin=1` y `@@server_id=1`                  | OK                                |
| 17  | Slave: `@@read_only=1` y `@@server_id=2`                 | OK                                |
| 18  | `SHOW GLOBAL STATUS LIKE 'Slave_running'`                | `ON`                              |
| 19  | `SHOW SLAVE STATUS` sin `Last_IO_Error`/`Last_SQL_Error` | vacío                             |
| 20  | GTID del slave ≥ GTID del master                         | sin lag                           |
| 21  | Split read/write activo en el `.env`                     | `true`                            |
| 22  | Log del operador disponible y con contenido              | legible                           |
| 23  | Llaves de host de SSH presentes en `/etc/ssh`              | al menos una                       |
| 23  | Llaves de host de SSH presentes en `/etc/ssh`              | al menos una                       |

**Exit codes**: `0` = todo OK · `1..N` = N checks fallidos · `64` = config inválida · `70` = error interno.

> Los nombres de contenedor **no están hardcodeados**: se resuelven desde el
> compose file, así que el smoke test sobrevive a los renombres.

---

## Lint de `pipefail` (`scripts/lint-pipefail.sh`)

Todos los módulos corren con `set -euo pipefail`. Eso significa que **cualquier**
comando dentro de una sustitución de comandos que devuelva `!= 0` aborta todo
el operador. Y varios devuelven `!= 0` en situaciones normales:

| Comando | Devuelve != 0 cuando... |
|---|---|
| `getent passwd <user>` | el usuario no existe (→ **exit 2**) |
| `grep <patron> <archivo>` | no hay coincidencias (→ exit 1) |
| `stat <archivo>` | el archivo no existe |
| `id <user>` | el usuario no existe |

Ese `getent` rompió un despliegue real: en la primera corrida el usuario admin
todavía no existía, `getent` devolvió 2, `pipefail` lo propago y el operador
murió con un `exit code 2` y un error genérico.

```bash
bash scripts/lint-pipefail.sh
# OK: ninguna asignacion desprotegida contra set -euo pipefail
```

El lint recorre `scripts/lib/*.sh` y `scripts/operator.sh`, une las asignaciones
multi-línea y reporta las que no están protegidas con `|| true` (o que no
manejan el valor vacío).

---

## Resumen final

Al terminar (`--all`, o `--module deploy` / `--module verificar`) se imprime un
resumen con lo que hay que saber: accesos, dónde están las llaves SSH, estado
de la replicación, rutas importantes y los próximos pasos.

```
══════════════════════════════════════════════════════════════════════
  RESUMEN DEL DESPLIEGUE — songbird-operator v0.3.0
══════════════════════════════════════════════════════════════════════

ACCESO
  Aplicación       http://<ip>:8000/
  Zabbix           http://<ip>:8000/zabbix/
  SSH              ssh -p 2222 songbird_admin@<ip>

LLAVES SSH
  Se GENERO un par de llaves en ESTE servidor.
  Privada          /home/songbird_admin/.ssh/id_ed25519
  Publica          /home/songbird_admin/.ssh/id_ed25519.pub
  Para obtenerla (desde la CONSOLA del servidor):
    sudo cat /home/songbird_admin/.ssh/id_ed25519
  ...

BASE DE DATOS (master-slave)
  Master (escritura)  songbird_db_master
  Slave  (lectura)    songbird_db_slave
  Replicación         ACTIVA (hilos IO y SQL corriendo)

RUTAS
  Proyecto        /opt/songbird-app
  Compose file    /opt/songbird-app/docker-compose.yml
  Variables       /opt/songbird-app/.env (600)
  Credenciales    /opt/songbird-operator/credentials.env (600)
  Log operador    /var/log/songbird/songbird-operator.log
  Backups         /var/backups/songbird/
  Estado (json)   /var/lib/songbird-operator/state.json

PROXIMOS PASOS
  1. Traé la llave privada SSH del servidor y borrala de ahí.
  2. Entrá a la app: http://<ip>:8000/
  3. Cambiá el password de Admin/zabbix en la UI de Zabbix.
  4. Vincular los hosts en Zabbix (proxy, web, db, db_slave).
  5. Probá el backup: sudo /usr/local/sbin/songbird-backup.sh
```

---

## Logs

Toda la salida del operador (INFO/OK/WARN/ERROR) queda en:

```
/var/log/songbird/songbird-operator.log
```

- Es **append-only**: cada ejecución agrega al final.
- Sin colores (a diferencia de la consola) → fácil de grepear.
- Rotado por `/etc/logrotate.d/songbird` (daily, 14 rotaciones, comprimido).
- La ruta también aparece en el banner de cada ejecución y en el resumen del
  smoke test.

```bash
# Últimas líneas, en vivo
sudo tail -f /var/log/songbird/songbird-operator.log

# Solo errores y warnings
sudo grep -E "ERROR|WARN" /var/log/songbird/songbird-operator.log | tail -n 20

# Cuándo se ejecutó un módulo
sudo grep "Ejecutando modulo: deploy" /var/log/songbird/songbird-operator.log
```

Los logs de los contenedores van aparte (los maneja Docker):

```bash
docker compose logs -f db-master db-slave
docker compose logs -f db-replication-init    # el que configura la replicación
```

---

## Tareas post-deploy (manuales del operador)

1. **Copiar tu clave pública SSH al host**:
   ```bash
   ssh-copy-id -p 2222 songbird_admin@<host>
   ```
2. **Cambiar el password `Admin/zabbix` por defecto** en la UI de Zabbix.
3. **Vincular los hosts sidecar de Zabbix** (esperar ~60s después del primer arranque):
   `songbird_proxy`, `songbird_web`, `songbird_db` (master) y `songbird_db_slave`.
   Template: `Linux by Zabbix agent 2`.
   IP: `docker inspect <container> | jq '.[0].NetworkSettings.IPAddress'`
4. **Validar el backup**:
   ```bash
   sudo /usr/local/sbin/songbird-backup.sh
   ls -lh /var/backups/songbird/
   ```
5. **Cerrar sesión de la VM de bootstrap** y reconectarte por el puerto nuevo:
   ```bash
   ssh -p 2222 songbird_admin@<host>
   ```

---

## Troubleshooting

### "Repo no encontrado en /opt/songbird-app"

Esa ruta ya no es obligatoria. El operador trabaja in-place sobre el repo
donde vive el script. Si el error aparece, es que el checkout no tiene
`docker-compose.yml` ni `.env.example`:

```bash
git clone https://github.com/nikolasribeiro/proyecto-final-egreso-2026.git ~/proyecto
cd ~/proyecto && sudo scripts/operator.sh --all
```

### "No se encontró <repo>/docker-compose.yml"

El checkout no tiene el compose file. Verificá que clonaste el repo completo
y que no estás en un subdirectorio.

### "PermitRootLogin no" o "PasswordAuthentication no" bloqueó el acceso

```bash
ls /etc/ssh/sshd_config.d/00-songbird-hardening.conf.bak-*
sudo mv /etc/ssh/sshd_config.d/00-songbird-hardening.conf.bak-<ts> /etc/ssh/sshd_config.d/00-songbird-hardening.conf
sudo sshd -t && sudo systemctl reload ssh
```

### La replicación no arranca

```bash
docker compose logs db-replication-init
docker compose exec db-slave mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SHOW SLAVE STATUS\G"
docker compose logs db-slave
```

Causas frecuentes:

- `MYSQL_REPLICATION_PASSWORD` vacía o distinta en master y slave.
- Volumen `mariadb_slave_data` con datos viejos → `docker compose down -v`
  y volver a levantar.

### Healthcheck de Zabbix no pasa

```bash
docker logs songbird_zabbix_server 2>&1 | tail -50
```

Causa común: credenciales MySQL mal configuradas en `.env`.

### DNS no resuelve `deb.debian.org`

`preflight` aborta. Verificá `/etc/resolv.conf`.

### Puertos 2222 / 8000 ocupados

```bash
sudo ss -ltnp | grep -E ':(2222|8000)\b'
sudo scripts/operator.sh --all --ssh-port 22022 --app-port 80
```

### Logs del operador

```bash
sudo tail -f /var/log/songbird/songbird-operator.log
sudo journalctl -u docker
```

---

## Seguridad

- **Nunca commitear** `.env`, `credentials.env`, `state.json` ni archivos `.bak`.
- El archivo `/opt/songbird-operator/credentials.env` (chmod 600) contiene los
  passwords generados — accesible solo por root.
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
/var/log/songbird/songbird-operator.log                 # log del operador
/var/log/journal/                                         # journal persistente
/var/lib/songbird-operator/state.json                     # estado de idempotencia
/opt/songbird-operator/credentials.env                    # passwords generados (chmod 600)
<checkout>/                                                # proyecto desplegado (in-place)
```

---

## Relación con `.docs/master-plan.md`

El script **NO** modifica el plan maestro. La sección de base de datos del plan
maestro describe la estrategia master-slave que este operador despliega
(replicación GTID + split read/write).
