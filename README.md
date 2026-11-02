# proyecto-final-egreso-2026

Proyecto Final de egreso de UTU 2026 ISBO - Grupo 3°ML

---

## Integrantes

- Juan de la Vega
- Leandro Conte
- Nicolas Pereyra
- Nicolas Ribeiro

---

## Arquitectura y Requisitos de Software

Este proyecto prescinde de entornos genéricos locales (como XAMPP) para garantizar la paridad absoluta entre los sistemas operativos de los desarrolladores (Linux/Windows) y preparar el terreno para el despliegue final en producción. Utilizamos una arquitectura 100% contenerizada basada en **Docker Compose / Podman**, compuesta por:

- **Proxy Inverso (Nginx:alpine):** Actúa como recepcionista del tráfico en el puerto 80, aportando seguridad perimetral y derivando las peticiones de forma transparente.
- **Servidor Web y Backend (PHP:8.2-apache):** Contenedor aislado que procesa la lógica de negocio sin contaminar el sistema operativo host.
- **Base de Datos (MariaDB:10.11):** Motor relacional principal del sistema, aislado de la red externa por seguridad.
- **Gestor de BD (phpMyAdmin):** Interfaz gráfica accesible mediante sub-ruta para iterar rápidamente sobre el SQL durante el desarrollo.

---

## Entorno de Desarrollo Recomendado (IDE)

Para mantener la coherencia y estandarización del código en el equipo, se recomienda utilizar **Visual Studio Code** con las siguientes extensiones instaladas:

- **PHP Intelephense:** Para autocompletado, formateo y detección de errores en el backend.
- **Docker:** Para gestionar los contenedores, visualizar logs y reiniciar servicios directamente desde el editor.
- **Prettier - Code formatter:** Para mantener un estilo uniforme en HTML, CSS y JavaScript Vanilla.

## Cómo levantar el proyecto (Entorno Local)

### Requisitos Previos

- Tener instalado **Docker** y **Docker Compose** (o Podman y Podman Compose).

### Pasos de Instalación

**1. Clona el proyecto**

```bash
git clone https://github.com/nikolasribeiro/proyecto-final-egreso-2026.git
```

**2. Variables de entorno**
Asegurate de generar tu archivo de configuración copiando el archivo de ejemplo. Podés hacerlo ejecutando en la terminal:

`cp .env.example .env`

Luego, abrí el nuevo archivo `.env` generado y completá los valores vacíos con tus credenciales locales. La estructura es la siguiente:

#### Configuraciones Generales
```text
APP_PORT=8000
BASE_PATH=http://localhost:${APP_PORT} # <- Dejar así en modo desarrollo
MYSQL_DATABASE=nombre_de_tu_bd
MYSQL_USER=tu_usuario
MYSQL_PASSWORD=tu_contraseña
MYSQL_ROOT_PASSWORD=tu_contraseña_root
```
#### Configuraciones de desarrollo
```text
PMA_HOST=db # <- Dejar como está
PMA_USER=tu_usuario
PMA_PASSWORD=tu_contraseña
```
#### Variables visibles en PHP
```text
APP_ENVIRONMENT=development
```

**3. Levantar la infraestructura**
Abrí la terminal en la raíz del proyecto y ejecutá:

```bash
docker-compose up -d
```

_(Nota: Si usás Podman, ejecutá `podman compose up -d`)_

### Accesos

Una vez que los contenedores estén levantados, accedé desde tu navegador:

- **Aplicación:** http://localhost:[puerto que hayas designado]
- **Gestor de Base de Datos (phpMyAdmin):** http://localhost:[puerto que hayas designado]/phpmyadmin/

### Apagar el entorno

Para detener los contenedores (sin perder los datos de la base de datos), ejecutá:
`docker-compose down`

---

## Despliegue automatizado (`scripts/operator.sh`)

`scripts/operator.sh` toma una **Debian 13 (trixie) limpia** y la deja con el
stack corriendo: SSH endurecido, UFW, usuarios, Docker, backups, logrotate y
el proyecto desplegado.

```bash
# ── Primera vez, como root (si no tenés sudo instalado) ───────────────
su -
apt-get update && apt-get install -y sudo git
exit

# ── Con sudo disponible ───────────────────────────────────────────────
# 1. Clonás el proyecto (el operador NO clona)
git clone https://github.com/nikolasribeiro/proyecto-final-egreso-2026.git ~/proyecto
cd ~/proyecto

# 2. Cargás tus variables
cp .env.example .env && nano .env

# 3. Corrés el operador desde el checkout
sudo scripts/operator.sh --all

# 4. Smoke test (24 checks)
sudo scripts/operator.sh --module verificar
```

> La primera ejecución va como root (`su -`) porque el operador necesita root
> y, sin `sudo` instalado, no hay forma de obtenerlo ni de instalarlo solo.

El operador trabaja **in-place** sobre el repo donde vive el script y
**respeta tu `.env`**: solo rellena lo que está vacío. Detalle completo en
[`scripts/README.md`](scripts/README.md).

### Permisos de `uploads/` (subida de documentos)

> **Límite de tamaño**: los PDFs pueden pesar hasta **50 MB**. El límite lo
> imponen tres lugares que tienen que coincidir: `client_max_body_size` en
> `nginx/default.conf` (52M, incluye el overhead del POST), `upload_max_filesize`
> y `post_max_size` en el `Dockerfile` (50M / 52M). El frontend lo conoce sin
> duplicar el número: el backend lo lee del `php.ini` y lo pasa en
> `data-max-bytes` (`src/Nucleo/ayudantes.php` → `tamano_maximo_subida()`).
> Si un archivo excede el límite, la UI lo avisa **antes** de subirlo.

`src/uploads/` es un **bind mount** del host: el contenedor `web` ve
exactamente los permisos del directorio en el host. El worker de Apache
escribe ahí como `www-data`, así que si el directorio no es escribible por
ese usuario, la subida de documentos falla con:

```
PHP Warning: move_uploaded_file(.../uploads/archivo.pdf): Failed to open stream: Permission denied
```

...mientras **el resto de la app funciona normal** (login, dashboard,
listados). El único síntoma es que el `POST /api/documentos` devuelve `400`.

Esto está resuelto en dos capas:

1. **`docker/entrypoint-web.sh`** (dentro del contenedor): en cada arranque
   ajusta el owner de `/var/www/html/uploads` a `www-data` (`0775`). Se hace
   **adentro** del contenedor a propósito: es el único lugar donde el uid de
   `www-data` es el correcto (con Docker rootful es el `33` del host; con
   Podman rootless es otro uid mapeado, y un `chown 33:33` desde el host
   sería equivocado).
2. **`scripts/lib/verificar.sh` (check #24)**: hace una escritura real en
   `/var/www/html/uploads` con el mismo usuario que usa la app. Si algún día
   vuelve a romperse, el smoke test falla en vez de descubrirlo en la UI.

> **Si modificaste el `Dockerfile`** (o el código) y el stack ya estaba
> `healthy`, el operador **no reconstruye la imagen** (optimiza el deploy).
> Reconstruí a mano:
>
> ```bash
> cd /home/debian/proyecto
> sudo docker compose --env-file .env build web
> sudo docker compose --env-file .env up -d web
> ```

### Permisos del checkout

El deploy deja el árbol del proyecto con una política consistente:

| Ruta | Owner | Modo | Por qué |
|---|---|---|---|
| `<repo>/` | el usuario que invocó el operador | `2775` | `root:root` lo dejaba sin escritura: un `git pull` que agregara archivos fallaba |
| `<repo>/src/…` | el usuario que clonó | `g+rw`, dirs `g+s` | grupo compartido `songbird-ops` |
| `<repo>/.env` | el usuario que clonó | `640` | grupo `songbird-ops`: el admin remoto lo **lee** para correr `docker compose` sin sudo (Compose carga el `.env` para interpolar variables) |
| `<repo>/src/uploads/` | `www-data` (`33`) | `0775` | lo ajusta el entrypoint del contenedor |

---

## Monitoreo (Zabbix)

El proyecto incluye **Zabbix 7.0** para monitorear los servicios `proxy`, `web` y `db` mediante agentes sidecar. El stack de Zabbix (server, web, su base MySQL y los 3 agentes) forma parte del arranque normal tanto en desarrollo como en producción. No se requiere ningún `--profile`.

### Levantar el entorno de desarrollo

```bash
docker compose up -d
```

Este comando inicia la aplicación, MariaDB, phpMyAdmin, Zabbix Server, Zabbix Web, su base MySQL y los tres agentes.

> El `nginx/default.conf` ya incluye el bloque `location /zabbix/` que hace proxy al `zabbix-web`, por lo que el dashboard queda accesible en `http://localhost:${APP_PORT}/zabbix/` igual que en producción.
>
> Asegurate de definir las variables `ZABBIX_*` en tu `.env` (ver bloque `MONITOREO (Zabbix)` en `.env.example`).

### Levantar el stack de producción

El proyecto usa **un único `docker-compose.yml`** para desarrollo y producción.
Lo que cambia es el `.env` (`APP_ENVIRONMENT`, credenciales, `APP_PORT`):

```bash
docker compose --env-file .env up -d
```

> Asegurate de que tu `.env` tenga todas las variables del bloque `MONITOREO (Zabbix)` definidas en `.env.example`.
> Para producción, `APP_ENVIRONMENT=production` y credenciales propias.

### Base de datos: estrategia Master-Slave

La base de datos opera con **replicación master-slave** (MariaDB 10.11):

| Nodo | Servicio | Rol | Puerto |
|---|---|---|---|
| **Master** | `db-master` | **Escritura** (alias de red `db`) | interno |
| **Slave** | `db-slave` | **Lectura** (réplica, `read_only`) | interno |
| Init | `db-replication-init` | Configura la replicación (one-shot) | — |

- Las **escrituras** (`INSERT`/`UPDATE`/`DELETE`/DDL) van siempre al **master**.
- Las **lecturas** (`SELECT`/`SHOW`) van al **slave**.
- La aplicación enruta de forma transparente: `Nucleo\Conexion` devuelve un
  `PDO` que decide el servidor según el tipo de sentencia. **No hace falta
  cambiar el código de los modelos.**
- Si el slave no está disponible, la app **cae al master** (fail-open): nunca
  se queda sin base de datos por un fallo de la réplica.
- Tras cada escritura, la app espera (con timeout acotado) a que la réplica
  aplique el cambio: garantiza *leer lo que escribí* entre requests.

Configuración **declarativa**: cada nodo declara sus parámetros en el
`command:` del `docker-compose.yml` (con el "por qué" de cada setting en los
comentarios). La única lógica —el `CHANGE MASTER TO`— vive en un único script:

```
database/replication/init-replication.sh   # CHANGE MASTER TO + START SLAVE
```

Variables de entorno (`.env`):

```text
DB_HOST=db-master                    # escritura
DB_SLAVE_HOST=db-slave              # lectura
DB_READ_REPLICA_ENABLED=true         # activa el split read/write
DB_REPLICA_WAIT_AFTER_WRITE=true     # espera a la réplica tras escribir
DB_REPLICA_WAIT_TIMEOUT_MS=2000
MYSQL_REPLICATION_USER=repl
MYSQL_REPLICATION_PASSWORD=          # la genera el operador si está vacía
```

Verificar el estado de la replicación:

```bash
docker compose exec db-slave mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SHOW SLAVE STATUS\G"
```

> La imagen oficial de MariaDB **no** trae el plugin de replicación
> semi-síncrona (`semisync_master.so` no existe en `mariadb:10.11`), por eso la
> consistencia de lectura se garantiza en la capa de aplicación comparando el
> GTID del master con el del slave.

### Verificación de arranque

Docker Compose espera a que cada servicio esté **healthy** (no solo "started") antes de iniciar sus dependientes. Esto elimina la race condition que provocaba un `502 Bad Gateway` transitorio al acceder a `/zabbix/` justo después del `up -d`.

En el **primer arranque**, Zabbix puede tardar más de un minuto mientras inicializa su base de datos. Verificá el estado con:

```bash
docker compose ps
```

Todos los servicios deben figurar como `Up ... (healthy)`. Para producción:

```bash
docker compose --env-file .env ps
```

Probá los dos accesos publicados:

```bash
curl -fsS "http://localhost:${APP_PORT}/"        > /dev/null
curl -fsS "http://localhost:${APP_PORT}/zabbix/" > /dev/null
```

Ambos comandos deben finalizar con código `0` (sin 502).

### Accesos

- **UI de Zabbix:** http://localhost:[APP_PORT]/zabbix/
  - Ejemplo: con `APP_PORT=8000` → http://localhost:8000/zabbix/
  - Usuario por defecto: `Admin`
  - Contraseña por defecto: `zabbix`
  - ⚠️ **Cambialas inmediatamente en el primer login** (Users → Admin → Change password).
- **Tráfico de agentes → server:** se realiza internamente en la red `songbird_network` (no expuesto al host).

### Vincular los servicios como hosts

Tras el primer arranque (esperá ~60 segundos a que el server inicialice la BD):

1. En la UI, ir a **Configuration → Hosts → Create host**.
2. Crear 3 hosts con estos datos:
   - `songbird_proxy` — interface: IP del contenedor `zabbix-agent-proxy`, port `10050`.
   - `songbird_web` — interface: IP del contenedor `zabbix-agent-web`, port `10050`.
   - `songbird_db` — interface: IP del contenedor `songbird_zabbix_agent_db`, port `10050`.
   - `songbird_db_slave` — interface: IP del contenedor `songbird_zabbix_agent_db_slave`, port `10050` (réplica).
3. Asignar el template **`Linux by Zabbix agent 2`** a cada host.
4. Esperar 1–2 minutos y verificar en **Monitoring → Latest data** que aparezcan métricas (CPU, memoria, red, filesystem).

### Configuración

Las credenciales y versiones de Zabbix se controlan vía variables en `.env` (bloque `MONITOREO (Zabbix)`). Ver `.env.example` para la lista completa.

### Solución de problemas

- **"Connection refused" al agregar un host:** verificá que el contenedor `zabbix-agent-*` correspondiente está corriendo y pertenece a la red `songbird_network`:
  ```bash
  docker compose ps
  docker compose ps
  ```
- **El server de Zabbix no arranca:** revisá los logs:
  ```bash
  docker compose logs zabbix-server
  docker compose logs zabbix-server
  ```
  Causa común: credenciales MySQL mal configuradas en `.env` (variables `ZABBIX_DB_PASSWORD` y `ZABBIX_DB_ROOT_PASSWORD`).
- **Un servicio queda `(unhealthy)` tras `up -d`:** esperá un minuto y revisá los logs del servicio (`docker compose logs <servicio>`). El `start_period` cubre la inicialización; si supera ese margen suele haber credenciales mal o el upstream no responde.
- **Resetear la contraseña del admin de Zabbix:**
  ```bash
  docker exec -u root songbird_zabbix_web bash -c "supervisorctl restart zabbix"
  ```

### Notas de seguridad

- **No expongo puertos innecesariamente al host**: el dashboard de Zabbix se accede vía `nginx` en `/zabbix/`, y la comunicación agente→server ocurre dentro de la red `songbird_network`. Solo el puerto `${APP_PORT}` queda publicado.
- En producción, el `.env` queda en `640` con el grupo `songbird-ops` (lo
  administra el operador): el owner lo edita y solo los miembros del grupo —
  el admin remoto entre ellos — lo leen. Si necesitás restrictarlo por
  completo, `chmod 600 .env` (el admin necesitará `sudo` para correr
  `docker compose`).

### En caso de tener problemas con algunos sitemas como por ejemplo Linux Mint

- sudo apt update
- sudo apt install docker-compose-plugin -y
- sudo usermod -aG docker $USER
- newgrp docker
- sudo apt install docker-compose-v2 -y
- sudo mkdir -p /usr/local/lib/docker/cli-plugins
- sudo curl -SL https://github.com/docker/compose/releases/latest/download/docker-compose-linux-x86_64 -o /usr/local/lib/docker/cli-plugins/docker-compose
- sudo chmod +x /usr/local/lib/docker/cli-plugins/docker-compose

### Para los casos de Debian

- sudo apt --fix-broken install
- docker compose up -d
- sudo systemctl start docker
- sudo systemctl enable docker
- sudo usermod -aG docker $USER
- newgrp docker
- sudo apt update
- sudo apt install docker.io -y
- sudo systemctl start docker
- sudo systemctl enable docker
- sudo systemctl status docker

### Para los casos donde el host es Windows y se requiere instalacion Docker
- ir a panel de control, caracteristicas de Windows, y verificar en las casillas; "Hiper-V" y #Subsistema de Windows para Linux" esten marcadas.
- instalar docker
- luego ir a VS o GitBash (dependiendo de como lo tengan instalado en su pc) e iniciar el los dockers con el comando docker compose up -d desde la rama indicada.
