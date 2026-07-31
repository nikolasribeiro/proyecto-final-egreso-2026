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

```bash
docker compose -f docker-compose.prod.yml --env-file .env up -d
```

> Asegurate de que tu `.env` tenga todas las variables del bloque `MONITOREO (Zabbix)` definidas en `.env.example`.

### Verificación de arranque

Docker Compose espera a que cada servicio esté **healthy** (no solo "started") antes de iniciar sus dependientes. Esto elimina la race condition que provocaba un `502 Bad Gateway` transitorio al acceder a `/zabbix/` justo después del `up -d`.

En el **primer arranque**, Zabbix puede tardar más de un minuto mientras inicializa su base de datos. Verificá el estado con:

```bash
docker compose ps
```

Todos los servicios deben figurar como `Up ... (healthy)`. Para producción:

```bash
docker compose -f docker-compose.prod.yml --env-file .env ps
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
   - `songbird_db` — interface: IP del contenedor `zabbix-agent-db`, port `10050`.
3. Asignar el template **`Linux by Zabbix agent 2`** a cada host.
4. Esperar 1–2 minutos y verificar en **Monitoring → Latest data** que aparezcan métricas (CPU, memoria, red, filesystem).

### Configuración

Las credenciales y versiones de Zabbix se controlan vía variables en `.env` (bloque `MONITOREO (Zabbix)`). Ver `.env.example` para la lista completa.

### Solución de problemas

- **"Connection refused" al agregar un host:** verificá que el contenedor `zabbix-agent-*` correspondiente está corriendo y pertenece a la red `songbird_network`:
  ```bash
  docker compose ps
  docker compose -f docker-compose.prod.yml ps
  ```
- **El server de Zabbix no arranca:** revisá los logs:
  ```bash
  docker compose logs zabbix-server
  docker compose -f docker-compose.prod.yml logs zabbix-server
  ```
  Causa común: credenciales MySQL mal configuradas en `.env` (variables `ZABBIX_DB_PASSWORD` y `ZABBIX_DB_ROOT_PASSWORD`).
- **Un servicio queda `(unhealthy)` tras `up -d`:** esperá un minuto y revisá los logs del servicio (`docker compose logs <servicio>`). El `start_period` cubre la inicialización; si supera ese margen suele haber credenciales mal o el upstream no responde.
- **Resetear la contraseña del admin de Zabbix:**
  ```bash
  docker exec -u root zabbix-web-dev bash -c "supervisorctl restart zabbix"
  docker exec -u root zabbix-web-prod bash -c "supervisorctl restart zabbix"
  ```

### Notas de seguridad

- **No expongo puertos innecesariamente al host**: el dashboard de Zabbix se accede vía `nginx` en `/zabbix/`, y la comunicación agente→server ocurre dentro de la red `songbird_network`. Solo el puerto `${APP_PORT}` queda publicado.
- En producción, hacé `chmod 600 .env` para restringir el acceso a credenciales.

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
