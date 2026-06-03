# Manual Técnico del Entorno de Desarrollo

> **Punto 5 de la rúbrica de la Primera Entrega** — Manual técnico
> detallado, paso a paso, que permite a cualquier persona replicar
> exactamente el mismo entorno de desarrollo desde cero.

## Alcance y audiencia

- **Alcance:** Linux (Ubuntu 22.04+, Debian 12+, Fedora 38+, Linux Mint 21+).
- **Audiencia:** integrantes del equipo Songbird y el docente evaluador
  de la materia Programación Full Stack. El manual está pensado para
  alguien sin experiencia previa con Docker: cada comando está copiado
  literalmente y cada paso tiene una verificación.
- **Fuera de alcance:** Windows nativo y macOS nativo. Ambos funcionan
  con Docker Desktop, pero su instalación no se cubre aquí.

---

## 1. Stack de software

El proyecto se ejecuta íntegramente dentro de contenedores Docker. Lo
único que se instala en el host es el motor de Docker y un editor.

| Componente | Versión | Origen | Notas |
|------------|---------|--------|-------|
| Docker Engine | 24.0+ | Instalación oficial (repositorio `download.docker.com`) | Único requisito en el host |
| Docker Compose | v2 (plugin) | Instalación oficial | Se invoca como `docker compose` (sin guión) |
| Nginx | 1.25 alpine | Imagen `nginx:alpine` | Reverse proxy |
| PHP | 8.2 | Imagen `php:8.2-apache` (build local desde `Dockerfile`) | Extensión `pdo_mysql` + módulo `rewrite` |
| MariaDB | 10.11 | Imagen `mariadb:10.11` | LTS |
| phpMyAdmin | latest | Imagen `phpmyadmin/phpmyadmin` | ⚠️ ver mejora pendiente abajo |
| Visual Studio Code | Última estable | .deb / .rpm desde `code.visualstudio.com` | Editor recomendado |

### Mejoras pendientes (no bloquean esta entrega)

- **Fijar tag de phpMyAdmin.** El `docker-compose.yml` usa
  `phpmyadmin/phpmyadmin` sin versión, lo cual toma `latest`. Esto
  puede romper el entorno en un rebuild futuro. Cambiar a
  `phpmyadmin/phpmyadmin:5.2.1` o similar.
- **`docker-compose.prod.yml`.** El comentario al inicio del
  `docker-compose.yml` actual menciona que en producción se usará
  un archivo `docker-compose.prod.yml` sin phpMyAdmin. Está pendiente
  para la siguiente entrega.
- **Inyectar `APP_ENVIRONMENT` al servicio `web`.** La variable existe
  en `.env.example` pero no se pasa al contenedor `web` (sí a `db` y
  `phpmyadmin`). Mientras tanto, `getenv('APP_ENVIRONMENT')` en PHP
  devuelve `false`.

---

## 2. Instalación de Docker en Linux

### 2.1 Ubuntu 22.04+ / Debian 12+

```bash
# 1. Desinstalar versiones anteriores (si las hay)
sudo apt remove docker docker-engine docker.io containerd runc

# 2. Instalar dependencias previas
sudo apt update
sudo apt install -y ca-certificates curl gnupg

# 3. Crear el directorio para la clave GPG oficial
sudo install -m 0755 -d /etc/apt/keyrings

# 4. Descargar y guardar la clave GPG oficial de Docker
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | \
  sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg

# 5. Agregar el repositorio oficial a las fuentes de APT
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/ubuntu \
  $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# 6. Instalar Docker Engine, CLI, containerd y el plugin Compose
sudo apt update
sudo apt install -y \
  docker-ce \
  docker-ce-cli \
  containerd.io \
  docker-buildx-plugin \
  docker-compose-plugin

# 7. Agregar tu usuario al grupo docker (evita usar sudo en cada comando)
sudo usermod -aG docker $USER
newgrp docker

# 8. Verificación
docker --version
docker compose version
docker run hello-world
```

> En Debian 12, reemplazar `ubuntu` por `debian` en los pasos 4 y 5.

### 2.2 Fedora 38+

```bash
# 1. Desinstalar versiones anteriores
sudo dnf remove docker \
  docker-client \
  docker-client-latest \
  docker-common \
  docker-latest \
  docker-latest-logrotate \
  docker-logrotate \
  docker-selinux \
  docker-engine-selinux \
  docker-engine

# 2. Instalar dnf-plugins-core
sudo dnf -y install dnf-plugins-core

# 3. Agregar el repositorio oficial
sudo dnf config-manager --add-repo \
  https://download.docker.com/linux/fedora/docker-ce.repo

# 4. Instalar Docker Engine
sudo dnf install -y \
  docker-ce \
  docker-ce-cli \
  containerd.io \
  docker-buildx-plugin \
  docker-compose-plugin

# 5. Iniciar y habilitar el servicio
sudo systemctl start docker
sudo systemctl enable docker

# 6. Agregar tu usuario al grupo docker
sudo usermod -aG docker $USER
newgrp docker

# 7. Verificación
docker --version
docker compose version
docker run hello-world
```

### 2.3 Linux Mint 21+ (basada en Ubuntu 22.04)

Aplicar los pasos de Ubuntu. Si el plugin `docker-compose-plugin` no
instala correctamente (caso conocido en Linux Mint), usar el fallback:

```bash
# Fallback: instalar docker-compose manualmente como binario
sudo apt update
sudo apt install -y docker.io
sudo systemctl start docker
sudo systemctl enable docker
sudo usermod -aG docker $USER
newgrp docker

sudo mkdir -p /usr/local/lib/docker/cli-plugins
sudo curl -SL \
  https://github.com/docker/compose/releases/latest/download/docker-compose-linux-x86_64 \
  -o /usr/local/lib/docker/cli-plugins/docker-compose
sudo chmod +x /usr/local/lib/docker/cli-plugins/docker-compose

# Verificación
docker --version
docker compose version
```

---

## 3. Configuración inicial del proyecto

Una vez que Docker está instalado, el entorno se levanta en cuatro
pasos.

```bash
# 1. Clonar el repositorio
git clone https://github.com/nikolasribeiro/proyecto-final-egreso-2026.git
cd proyecto-final-egreso-2026

# 2. Crear el archivo .env a partir de la plantilla
cp .env.example .env
# (opcional) editar .env si se quiere cambiar APP_PORT (default 8000)
# o las contraseñas de MariaDB

# 3. Levantar la infraestructura en segundo plano
docker compose up -d

# 4. Verificar que los 4 servicios están corriendo
docker compose ps
```

### ¿Qué hace cada paso?

- **Clonar:** trae el código fuente, el `Dockerfile`, el
  `docker-compose.yml` y la configuración de Nginx.
- **Copiar `.env.example` a `.env`:** el `.env` real nunca se commitea
  (está en `.gitignore`); la plantilla `.env.example` define todas las
  variables necesarias. Editar `.env` permite cambiar el puerto de
  exposición (`APP_PORT`, default `8000`) o las contraseñas de la BD.
- **`docker compose up -d`:** construye la imagen del servicio `web`
  (PHP + Apache) desde el `Dockerfile` local y descarga las imágenes
  públicas de los otros tres servicios (`nginx:alpine`,
  `mariadb:10.11`, `phpmyadmin/phpmyadmin`).
- **`docker compose ps`:** muestra el estado de los 4 contenedores.
  Deben estar todos con estado `running` o `healthy`.

---

## 4. Arquitectura del stack

```
┌────────────────────────────────────────────────────────────────┐
│ Host Linux                                                     │
│                                                                │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  proxy  (nginx:alpine)   ◄── ÚNICO puerto expuesto       │  │
│  │     :80 dentro de la red Docker                          │  │
│  │     mapeado a ${APP_PORT} en el host                     │  │
│  │     ├─ /             → http://web:80                     │  │
│  │     └─ /phpmyadmin/  → http://phpmyadmin:80/            │  │
│  ├──────────────────────────────────────────────────────────┤  │
│  │  web  (php:8.2-apache, build local)                      │  │
│  │     pdo_mysql + mod_rewrite                              │  │
│  │     bind mount ./src → /var/www/html                     │  │
│  │     NO expone puerto al host                             │  │
│  ├──────────────────────────────────────────────────────────┤  │
│  │  db  (mariadb:10.11)                                     │  │
│  │     volumen mariadb_data → /var/lib/mysql                │  │
│  │     NO expone puerto al host                             │  │
│  ├──────────────────────────────────────────────────────────┤  │
│  │  phpmyadmin  (phpmyadmin/phpmyadmin)                     │  │
│  │     accesible solo vía /phpmyadmin/ del proxy            │  │
│  │     NO expone puerto al host                             │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                │
│  Host ◄──── solo ────► APP_PORT (default 8000) ◄────► proxy    │
└────────────────────────────────────────────────────────────────┘
```

### Por qué Nginx como reverse proxy

- **Un solo puerto expuesto al host** (`APP_PORT` → :80 del proxy).
  El resto de servicios (PHP, MariaDB, phpMyAdmin) están en la red
  interna de Docker y no son alcanzables desde el exterior.
- **Coherente con la arquitectura de producción** del DTI del Hospital
  de Clínicas, donde un Nginx/WAF de borde ya cumple ese rol.
- **Permite múltiples sub-rutas** (`/`, `/phpmyadmin/`, futuras APIs)
  sin abrir más puertos en el host ni reenviar tráfico manualmente.
- **Reenvío de cabeceras** (`Host`, `X-Real-IP`, `X-Forwarded-For`,
  `X-Forwarded-Proto`) para que el backend PHP pueda conocer la IP
  real del visitante y operar correctamente detrás del proxy.

### Por qué Docker (y no XAMPP / LAMP nativo)

- **Paridad entre sistemas operativos** del equipo: hay integrantes
  en Linux, otros en Windows. Con Docker, el entorno es idéntico
  para todos.
- **Paridad dev / prod:** el `docker-compose.yml` de desarrollo
  apunta exactamente a las mismas imágenes y versiones que se
  usarán en el servidor del hospital. Lo que funciona en
  desarrollo funciona en producción.
- **Reproducibilidad:** nuevas versiones de PHP o MariaDB se prueban
  cambiando un único tag en el compose, sin tocar el sistema
  operativo del host.
- **Compatibilidad con Podman:** la misma configuración funciona con
  `podman compose up -d` sin modificaciones, lo que es importante
  para entornos donde Docker Desktop no está disponible.

---

## 5. Configuración de Visual Studio Code

Visual Studio Code es el editor recomendado para mantener coherencia
en el equipo. Las extensiones a continuación están alineadas con el
stack del proyecto.

### Extensiones recomendadas

| Extensión | ID en Marketplace | Uso |
|-----------|-------------------|-----|
| PHP Intelephense | `bmewburn.vscode-intelephense-client` | Autocompletado, formato, análisis estático de PHP. Sustituye a `php.suggest.basic` (más inteligente y rápido). |
| Live Server | `ritwickdey.liveserver` | Preview de vistas estáticas durante el maquetado. |
| GitLens | `eamodio.gitlens` | Historial de commits, blame inline, búsqueda semántica. |
| Prettier - Code formatter | `esbenp.prettier-vscode` | Formato HTML, CSS, Markdown, JSON. |
| Docker | `ms-azuretools.vscode-docker` | Gestión visual de contenedores, logs, restart desde el editor. |
| MySQL | `cweijan.vscode-mysql-client2` | Conexión a MariaDB desde el editor para iterar SQL sin abrir phpMyAdmin. |

### Sugerencia automática al abrir el proyecto

Para que VS Code sugiera las extensiones al abrir el repo, crear el
archivo `.vscode/extensions.json` con la lista anterior. Este archivo
**sí se commitea** (es configuración de equipo, no personal), pero
la carpeta `.vscode/` con `settings.json` del usuario **no se
commitea** (está en `.gitignore`).

```json
{
  "recommendations": [
    "bmewburn.vscode-intelephense-client",
    "ritwickdey.liveserver",
    "eamodio.gitlens",
    "esbenp.prettier-vscode",
    "ms-azuretools.vscode-docker",
    "cweijan.vscode-mysql-client2"
  ]
}
```

### Configuración recomendada del editor (settings locales)

Este fragmento va en el `settings.json` **personal** (no se commitea):

```json
{
  "editor.formatOnSave": true,
  "editor.defaultFormatter": "esbenp.prettier-vscode",
  "[php]": {
    "editor.defaultFormatter": "bmewburn.vscode-intelephense-client"
  },
  "php.suggest.basic": false,
  "files.eol": "\n"
}
```

---

## 6. Lista de verificación final (Checklist)

Ejecutar cada punto en orden. Todos deben pasar antes de empezar a
desarrollar.

| # | Verificación | Comando / Acción | Resultado esperado |
|---|--------------|------------------|--------------------|
| 1 | 4 servicios corriendo | `docker compose ps` | 4 líneas con estado `running` o `healthy` |
| 2 | App accesible | Abrir `http://localhost:8000` en el navegador | Página de inicio del proyecto Hospital de Clínicas |
| 3 | phpMyAdmin accesible | Abrir `http://localhost:8000/phpmyadmin/` | Pantalla de login de phpMyAdmin (user: `root`, pass: la de `MYSQL_ROOT_PASSWORD` en `.env`) |
| 4 | Versión de PHP correcta | `docker compose exec web php -v` | Salida que comienza con `PHP 8.2.x` |
| 5 | Extensión `pdo_mysql` instalada | `docker compose exec web php -m \| grep pdo_mysql` | Línea `pdo_mysql` |
| 6 | Módulo `rewrite` activo en Apache | `docker compose exec web apache2ctl -M \| grep rewrite` | Línea `rewrite_module (shared)` |
| 7 | Conexión a MariaDB | `docker compose exec db mariadb -u root -p$MYSQL_ROOT_PASSWORD` (reemplazar por la contraseña real) | Prompt `MariaDB [(none)]>` |
| 8 | Volumen persistente | `docker volume ls \| grep mariadb_data` | Aparece el volumen `proyecto-final-egreso-2026_mariadb_data` |
| 9 | Logs del proxy sin errores | `docker compose logs proxy --tail=20` | Sin líneas con `error` o `emerg` |
| 10 | Logs del web sin errores fatales | `docker compose logs web --tail=20` | Sin `PHP Fatal error` ni `AH00558` |

Si todos los puntos pasan, el entorno está listo para desarrollar.

---

## 7. Problemas frecuentes

| # | Síntoma | Causa probable | Solución |
|---|---------|----------------|----------|
| 1 | `permission denied` al usar `docker` | El usuario actual no está en el grupo `docker` | `sudo usermod -aG docker $USER` y reiniciar sesión (o `newgrp docker` en la misma terminal) |
| 2 | `port 8000 already in use` | Otro proceso usa el puerto en el host | Cambiar `APP_PORT` en `.env` (por ejemplo, `APP_PORT=8080`) y `docker compose down && docker compose up -d` |
| 3 | `pdo_mysql not found` | Imagen cacheada con un build viejo | Forzar rebuild: `docker compose build --no-cache web && docker compose up -d` |
| 4 | Cambios en `.env` no se reflejan | Los contenedores se levantaron con las variables viejas | `docker compose down && docker compose up -d` (los volúmenes y bind mounts se conservan) |
| 5 | phpMyAdmin no carga (404 o pantalla en blanco) | `PMA_ABSOLUTE_URI` mal seteada o inconsistente con el `APP_PORT` real | Verificar que `BASE_PATH` en `.env` diga `http://localhost:${APP_PORT}` y que `APP_PORT` sea el mismo que usás en el navegador |
| 6 | `Cannot connect to the Docker daemon` | El servicio Docker no está corriendo | `sudo systemctl start docker && sudo systemctl enable docker` |
| 7 | `failed to solve: error getting credentials` | `docker login` previo con credenciales vencidas | `docker logout` y reintentar |
| 8 | La app muestra `403 Forbidden` en todas las rutas | Apache no tiene `AllowOverride All` para el `.htaccess` del router | El `Dockerfile` ya ejecuta `a2enmod rewrite`, pero si rebuildeás la imagen, verificar que no se haya perdido. Solución: `docker compose build --no-cache web` |
| 9 | Cambios en archivos PHP no se ven reflejados | El bind mount `./src` no se montó | `docker compose down && docker compose up -d`. Verificar con `docker compose exec web ls /var/www/html` que liste los archivos |
| 10 | Error de permisos en Linux Fedora/RHEL (SELinux) | El flag `:z` en los bind mounts se omitió | El `docker-compose.yml` ya incluye `:z` en `./src` y `./nginx/default.conf`. Si modificás los volúmenes, mantené el flag |

### Troubleshooting específico Linux Mint / Debian

Algunos pasos de la sección 2.1 no aplican a Linux Mint. Si
`apt install docker-ce` falla por paquetes faltantes, seguir la
secuencia de fallback documentada en 2.3.

Si el plugin `docker-compose-plugin` no se encuentra en los
repositorios, instalar `docker.io` desde los repos oficiales de
Debian y luego el binario de docker-compose manualmente (ver 2.3).

### Troubleshooting específico Fedora / RHEL

SELinux puede bloquear el bind mount si no se usa el flag `:z`. El
`docker-compose.yml` actual ya lo incluye, pero si se modifica la
sección `volumes`, **mantener el flag** para evitar denegaciones.

Si `dnf` reporta conflictos con `container-selinux`, actualizar
primero: `sudo dnf upgrade -y container-selinux`.

---

## 8. Apagar y limpiar

| Acción | Comando | Efecto |
|--------|---------|--------|
| Detener sin perder datos | `docker compose down` | Apaga los 4 contenedores. Conserva el volumen `mariadb_data`. |
| Detener y borrar BD | `docker compose down -v` | Apaga los contenedores **y** borra el volumen de MariaDB. **Se pierden todos los datos.** Usar solo si vas a reiniciar de cero. |
| Reconstruir solo el servicio `web` | `docker compose build --no-cache web && docker compose up -d web` | Reconstruye la imagen PHP desde el `Dockerfile` sin tocar la BD. Útil tras cambios en dependencias nativas (`pdo_mysql`, `mod_rewrite`). |
| Ver uso de disco | `docker system df` | Muestra cuánto espacio consumen imágenes, contenedores y volúmenes. |
| Limpiar recursos no usados | `docker system prune -a` | ⚠️ Borra todas las imágenes y contenedores no usados. Cuidado si tenés otros proyectos Docker. |

---

## 9. Próximos pasos tras levantar el entorno

Una vez que la checklist de la sección 6 pasa en verde:

1. **Revisar el modelado de datos** en
   [`Modelado_Datos.md`](./Modelado_Datos.md) para entender las
   entidades que se crearán en MariaDB.
2. **Crear la base de datos y las tablas** iniciales (DDL propuesto en
   la sección 5 de `Modelado_Datos.md`).
3. **Empezar a desarrollar** sobre la rama creada a partir de un issue
   del tablero, siguiendo la Guía de Contribución de la wiki.

---

## Referencias

- [Documentación oficial de Docker para Ubuntu](https://docs.docker.com/engine/install/ubuntu/)
- [Documentación oficial de Docker para Fedora](https://docs.docker.com/engine/install/fedora/)
- [Documentación oficial de Docker Compose](https://docs.docker.com/compose/)
- [Imagen oficial `php:8.2-apache` en Docker Hub](https://hub.docker.com/_/php)
- [Imagen oficial `mariadb:10.11` en Docker Hub](https://hub.docker.com/_/mariadb)
- [Imagen oficial `nginx:alpine` en Docker Hub](https://hub.docker.com/_/nginx)
- [Imagen oficial `phpmyadmin/phpmyadmin` en Docker Hub](https://hub.docker.com/r/phpmyadmin/phpmyadmin)
