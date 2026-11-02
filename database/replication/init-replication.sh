#!/usr/bin/env bash
# -----------------------------------------------------------------------------
# database/replication/init-replication.sh
# Inicializa la replicacion master -> slave. Contenedor one-shot.
#
# Es el equivalente "declarativo" del CHANGE MASTER TO manual. Se ejecuta una
# sola vez por despliegue (restart: "no") y:
#
#   1. espera a que master y slave acepten conexiones
#   2. crea/garantiza el usuario de replicacion en el master
#   3. configura el slave con CHANGE MASTER TO (auto-posicionado por GTID)
#   4. arranca la replicacion y espera a que los hilos IO y SQL corran
#
# Es IDEMPOTENTE: si la replicacion ya esta activa contra el master correcto,
# sale con 0 sin tocar nada.
#
# Variables de entorno (las inyecta docker-compose.yml):
#   MASTER_HOST, SLAVE_HOST, MYSQL_REPLICATION_USER,
#   MYSQL_REPLICATION_PASSWORD, MYSQL_ROOT_PASSWORD, REPL_WAIT_TIMEOUT
# -----------------------------------------------------------------------------
set -euo pipefail

MASTER_HOST="${MASTER_HOST:-db-master}"
SLAVE_HOST="${SLAVE_HOST:-db-slave}"
REPL_USER="${MYSQL_REPLICATION_USER:-repl}"
REPL_PASSWORD="${MYSQL_REPLICATION_PASSWORD:?MYSQL_REPLICATION_PASSWORD es obligatoria}"
ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:?MYSQL_ROOT_PASSWORD es obligatoria}"
# Segundos maximos de espera para que la replicacion quede activa.
REPL_WAIT_TIMEOUT="${REPL_WAIT_TIMEOUT:-120}"

log() {
    printf '[replication-init] %s\n' "$*"
}

# --- helpers de conexion (TCP, no socket: los nodos viven en otros contenedores)
master_sql() {
    mariadb -h "$MASTER_HOST" -u root -p"$ROOT_PASSWORD" --protocol=tcp -N -B -e "$1"
}

slave_sql() {
    mariadb -h "$SLAVE_HOST" -u root -p"$ROOT_PASSWORD" --protocol=tcp -N -B -e "$1"
}

# Extrae un campo puntual de `SHOW SLAVE STATUS` (devuelve ~60 columnas, asi
# que se parsea por nombre de columna y no por posicion).
slave_status_field() {
    local field="$1"
    mariadb -h "$SLAVE_HOST" -u root -p"$ROOT_PASSWORD" --protocol=tcp -B \
        -e "SHOW SLAVE STATUS;" 2>/dev/null \
        | awk -F'\t' -v f="$field" '
            NR == 1 { for (i = 1; i <= NF; i++) if ($i == f) c = i; next }
            { if (c) print $c }
        ' || true
}

# Espera a que un servidor acepte conexiones.
wait_for_server() {
    local host="$1" label="$2" i
    for ((i = 0; i < 60; i++)); do
        if mariadb-admin ping -h "$host" -u root -p"$ROOT_PASSWORD" \
            --protocol=tcp --silent >/dev/null 2>&1; then
            log "$label ($host) respondio"
            return 0
        fi
        sleep 2
    done
    return 1
}

# --- 1. Esperar a los dos nodos -------------------------------------------
log "Esperando al master..."
if ! wait_for_server "$MASTER_HOST" "master"; then
    log "ERROR: el master ($MASTER_HOST) no acepto conexiones"
    exit 1
fi

log "Esperando al slave..."
if ! wait_for_server "$SLAVE_HOST" "slave"; then
    log "ERROR: el slave ($SLAVE_HOST) no acepto conexiones"
    exit 1
fi

# --- 2. Usuario de replicacion en el master ------------------------------
#
# OJO con `CREATE USER IF NOT EXISTS`: si el usuario YA existe, MySQL/MariaDB
# SKIPPEA la sentencia COMPLETA (incluido el IDENTIFIED BY), asi que el
# password viejo se queda. Si despues el .env tiene otro password, el slave
# se configura con el nuevo y la autenticacion falla: la replica queda
# "Slave_running=OFF" sin explicacion. Por eso se fuerza con ALTER USER.
log "Garantizando el usuario de replicacion '$REPL_USER' en el master..."
master_sql "CREATE USER IF NOT EXISTS '${REPL_USER}'@'%' IDENTIFIED BY '${REPL_PASSWORD}';"
master_sql "ALTER USER '${REPL_USER}'@'%' IDENTIFIED BY '${REPL_PASSWORD}';"
master_sql "GRANT REPLICATION SLAVE, REPLICATION CLIENT ON *.* TO '${REPL_USER}'@'%';"
master_sql "FLUSH PRIVILEGES;"

# --- 3. Estado actual del slave -------------------------------------------
io_running="$(slave_status_field Slave_IO_Running)"
sql_running="$(slave_status_field Slave_SQL_Running)"
master_host_cfg="$(slave_status_field Master_Host)"

if [[ "$io_running" == "Yes" && "$sql_running" == "Yes" && "$master_host_cfg" == "$MASTER_HOST" ]]; then
    log "La replicacion ya estaba activa contra $master_host_cfg. Nada que hacer."
    exit 0
fi

# --- 4. (Re)configurar el slave -------------------------------------------
log "Configurando el slave (CHANGE MASTER TO con auto-posicionado GTID)..."
slave_sql "STOP SLAVE;" >/dev/null 2>&1 || true
slave_sql "RESET SLAVE ALL;" >/dev/null 2>&1 || true
slave_sql "CHANGE MASTER TO
    MASTER_HOST='${MASTER_HOST}',
    MASTER_USER='${REPL_USER}',
    MASTER_PASSWORD='${REPL_PASSWORD}',
    MASTER_USE_GTID=slave_pos,
    MASTER_CONNECT_RETRY=10;"
slave_sql "START SLAVE;"

# --- 5. Esperar a que la replicacion quede activa -------------------------
log "Esperando a que los hilos IO y SQL se actives..."
deadline=$((SECONDS + REPL_WAIT_TIMEOUT))
while (( SECONDS < deadline )); do
    io_running="$(slave_status_field Slave_IO_Running)"
    sql_running="$(slave_status_field Slave_SQL_Running)"
    if [[ "$io_running" == "Yes" && "$sql_running" == "Yes" ]]; then
        log "Replicacion ACTIVA (Slave_IO_Running=$io_running, Slave_SQL_Running=$sql_running)"
        exit 0
    fi
    sleep 2
done

log "ERROR: la replicacion no se activo en ${REPL_WAIT_TIMEOUT}s"
log "Last_IO_Error:  $(slave_status_field Last_IO_Error)"
log "Last_SQL_Error: $(slave_status_field Last_SQL_Error)"
exit 1
