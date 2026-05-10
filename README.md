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
Asegurate copiar el archivo: `.env.example` y crear uno nuevo llamado `.env` y copia los valores de las variables de entorno que necesites para tu proyecto.

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
