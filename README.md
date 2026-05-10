# proyecto-final-egreso-2026

Proyecto Final de egreso de UTU 2026 ISBO - Grupo 3°ML

---

## Integrantes

- Juan de la Vega
- Leandro Conte
- Nicolas Pereyra
- Nicolas Ribeiro

---

## Cómo levantar el proyecto (Entorno Local)

### Requisitos Previos

- Tener instalado **Docker** y **Docker Compose** (o Podman y Podman Compose).

### Pasos de Instalación

**1. Clona el proyecto**

```bash
git clone https://github.com/nikolasribeiro/proyecto-final-egreso-2026.git
```

**2. Variables de entorno**
Asegurate de renombrar el archivo `.env.example` a `.env` en la raíz del proyecto (al lado del `docker-compose.yml`) con los puertos y credenciales correspondientes.

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