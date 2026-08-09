<?php

namespace Modelos;

use Nucleo\Conexion;
use Nucleo\Sesion;
use PDO;
use Throwable;

class ModeloTraslado
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexion::obtenerInstancia();
    }

    /**
     * Devuelve solicitudes de traslado aplicando un filtro de estado y, opcionalmente,
     * una lista blanca de prioridades.
     *
     * $filtro acepta:
     *   - 'todos'       (default) → sin filtro, devuelve todas las filas.
     *   - 'activos'     → solo PENDIENTE / EN_TRANSITO.
     *   - 'completados' → solo FINALIZADO / CANCELADO.
     * Cualquier otro valor cae al default 'todos' silenciosamente.
     *
     * $prioridades: array con valores permitidos ('verde', 'amarillo', 'rojo').
     * Si está vacío, no se filtra por prioridad. Valores desconocidos se descartan.
     */
    public function obtenerTodos(string $filtro = 'todos', array $prioridades = []): array
    {
        $condiciones = [];

        $condiciones[] = match ($filtro) {
            'activos'     => "et.estado IN ('PENDIENTE', 'EN_TRANSITO')",
            'completados' => "et.estado IN ('FINALIZADO', 'CANCELADO')",
            default       => null,
        };

        if (!empty($prioridades)) {
            $validas = array_values(array_intersect($prioridades, ['verde', 'amarillo', 'rojo']));
            if (!empty($validas)) {
                $lista = "'" . implode("','", $validas) . "'";
                $condiciones[] = "st.prioridad IN ({$lista})";
            }
        }

        $condiciones = array_filter($condiciones, fn($c) => $c !== null);
        $where = empty($condiciones) ? '' : 'WHERE ' . implode(' AND ', $condiciones);

        $sql = "SELECT st.id, st.tipo, st.prioridad, st.estado_critico,
                       st.requiere_camilla, st.volver_al_origen,
                       st.fecha_hora_salida, st.id_estado,
                       o.nombre_lugar AS origen,
                       et.estado AS estado_nombre,
                       v.matricula,
                       tv.descripcion AS tipo_vehiculo,
                       CONCAT(uc.nombre, ' ', uc.apellido) AS conductor,
                       (SELECT GROUP_CONCAT(u2.nombre_lugar ORDER BY dt.orden SEPARATOR ' → ')
                          FROM destinos_traslado dt
                          JOIN ubicaciones u2 ON dt.id_ubicacion = u2.id
                          WHERE dt.id_solicitud = st.id) AS destinos_texto
                FROM solicitud_traslados st
                JOIN ubicaciones o ON st.id_ubicacion_origen = o.id
                JOIN estado_traslados et ON st.id_estado = et.id
                JOIN vehiculos v ON st.id_vehiculo = v.id
                JOIN tipo_vehiculo tv ON v.id_tipo_vehiculo = tv.id
                JOIN usuarios uc ON st.ci_chofer = uc.ci
                {$where}
                ORDER BY st.fecha_hora_salida DESC";

        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Devuelve una solicitud con sus destinos y reportes anidados.
     * Calcula paso_info (descriptor estructurado de la próxima acción)
     * según el estado de cada destino.
     */
    public function obtenerPorId(int $id): ?array
    {
        $sql = "SELECT st.*,
                       o.nombre_lugar AS origen,
                       et.estado AS estado_nombre,
                       v.matricula,
                       tv.descripcion AS tipo_vehiculo,
                       CONCAT(uc.nombre, ' ', uc.apellido) AS chofer_nombre,
                       CONCAT(ue.nombre, ' ', ue.apellido) AS enfermero_nombre
                FROM solicitud_traslados st
                JOIN ubicaciones o ON st.id_ubicacion_origen = o.id
                JOIN estado_traslados et ON st.id_estado = et.id
                JOIN vehiculos v ON st.id_vehiculo = v.id
                JOIN tipo_vehiculo tv ON v.id_tipo_vehiculo = tv.id
                JOIN usuarios uc ON st.ci_chofer = uc.ci
                LEFT JOIN usuarios ue ON st.ci_enfermero = ue.ci
                WHERE st.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $traslado = $stmt->fetch();
        if (!$traslado) {
            return null;
        }

        // Destinos
        $dStmt = $this->db->prepare(
            "SELECT dt.id, dt.orden, dt.estado_destino,
                    dt.fecha_llegada_estimada, dt.fecha_llegada_efectiva,
                    u.id AS id_ubicacion, u.nombre_lugar AS nombre, u.direccion
             FROM destinos_traslado dt
             JOIN ubicaciones u ON dt.id_ubicacion = u.id
             WHERE dt.id_solicitud = :id
             ORDER BY dt.orden"
        );
        $dStmt->execute(['id' => $id]);
        $destinos = $dStmt->fetchAll();

        // Reportes por destino
        $rStmt = $this->db->prepare(
            "SELECT rd.id, rd.id_destino, rd.tipo_problema, rd.mensaje, rd.fecha_reporte
             FROM reportes_destino rd
             JOIN destinos_traslado dt ON rd.id_destino = dt.id
             WHERE dt.id_solicitud = :id
             ORDER BY rd.fecha_reporte DESC"
        );
        $rStmt->execute(['id' => $id]);
        $reportes = $rStmt->fetchAll();

        foreach ($destinos as &$d) {
            $d['reportes'] = array_values(array_filter(
                $reportes,
                fn($r) => (int)$r['id_destino'] === (int)$d['id']
            ));
        }
        unset($d);

        // Marcar la fila de regreso (última fila cuando volver_al_origen=true
        // y su id_ubicacion coincide con el origen del traslado). El modelo ya
        // la persiste así en crearSolicitud(); aquí solo etiquetamos para que
        // la UI y el cliente la distingan de los destinos "normales".
        $volverAlOrigen = !empty($traslado['volver_al_origen']);
        $origenId = isset($traslado['id_ubicacion_origen']) ? (int)$traslado['id_ubicacion_origen'] : null;
        foreach ($destinos as $idx => &$d) {
            $d['es_retorno'] = false;
        }
        unset($d);
        if ($volverAlOrigen && !empty($destinos)) {
            $lastIdx = array_key_last($destinos);
            if (
                $origenId !== null
                && (int)$destinos[$lastIdx]['id_ubicacion'] === $origenId
            ) {
                $destinos[$lastIdx]['es_retorno'] = true;
            }
        }

        // Coerción explícita de tipos para que el JSON que consume el cliente
        // llegue con booleanos y enteros reales (no "0"/"1" como strings,
        // que rompen `!!` en JS).
        $traslado['volver_al_origen'] = (bool)$volverAlOrigen;
        $traslado['estado_critico'] = (bool)($traslado['estado_critico'] ?? false);
        $traslado['requiere_camilla'] = (bool)($traslado['requiere_camilla'] ?? false);
        foreach ($destinos as &$d) {
            $d['id'] = (int)$d['id'];
            $d['orden'] = (int)$d['orden'];
            $d['id_ubicacion'] = (int)$d['id_ubicacion'];
            $d['reportes'] = $d['reportes'] ?? [];
        }
        unset($d);

        // Estado normalizado en lowercase para el cliente. estado_nombre sigue
        // siendo la etiqueta legible ("PENDIENTE", "EN_TRÁNSITO", ...).
        $traslado['estado'] = strtolower((string)($traslado['estado_nombre'] ?? 'pendiente'));

        $traslado['destinos'] = $destinos;
        $traslado['paso_info'] = $this->calcularPasoInfo($traslado, $destinos);

        return $traslado;
    }

    /**
     * Crea una solicitud transaccional:
     *  - INSERT en solicitud_traslados con todos los campos clínicos.
     *  - INSERT N rows en destinos_traslado con orden incremental.
     *  - Si volver_al_origen=true, agrega un destino final = origen.
     *  - Registra auditoría.
     */
    public function crearSolicitud(array $d): int
    {
        $this->db->beginTransaction();
        try {
            $origenId = (int)($d['id_ubicacion_origen'] ?? 1);
            $destinos = $d['destinos'] ?? [];
            if (empty($destinos)) {
                throw new \InvalidArgumentException('Se requiere al menos un destino.');
            }
            $primerDestino = (int)$destinos[0]['id'];

            // Validación: el camión SOLO está disponible para equipamiento
            $stmtTipoVeh = $this->db->prepare(
                "SELECT tv.descripcion
                 FROM vehiculos v
                 JOIN tipo_vehiculo tv ON v.id_tipo_vehiculo = tv.id
                 WHERE v.id = :id"
            );
            $stmtTipoVeh->execute(['id' => (int)$d['id_vehiculo']]);
            $tipoVehiculo = (string)($stmtTipoVeh->fetchColumn() ?: '');
            if (
                str_contains($tipoVehiculo, 'Camión') &&
                ($d['tipo'] ?? '') !== 'equipamiento'
            ) {
                throw new \InvalidArgumentException(
                    'El camión solo está disponible para traslados de equipamiento.'
                );
            }

            $prioridad = !empty($d['estadoCritico']) ? 'rojo'
                       : (!empty($d['requiereCamilla']) ? 'amarillo' : 'verde');

            $fechaSalida = date('Y-m-d H:i:s');
            $fechaEstimada = date('Y-m-d H:i:s', strtotime('+45 minutes'));

            $sql = "INSERT INTO solicitud_traslados (
                        id_ubicacion_origen, id_ubicacion_destino,
                        fecha_hora_salida, fecha_hora_llegada_estimada,
                        id_estado, id_vehiculo,
                        ci_chofer, ci_enfermero, ci_administrativo, ci_paciente_externo,
                        tipo, estado_critico, requiere_camilla, tipo_diagnostico,
                        jerarquia_enfermero, volver_al_origen, prioridad
                    ) VALUES (
                        :origen, :primer,
                        :salida, :estimada,
                        :estado, :vehiculo,
                        :chofer, :enfermero, :admin, :paciente,
                        :tipo, :critico, :camilla, :diag,
                        :jerarquia, :volver, :prioridad
                    )";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'origen'    => $origenId,
                'primer'    => $primerDestino,
                'salida'    => $fechaSalida,
                'estimada'  => $fechaEstimada,
                // Resolvemos el id del estado PENDIENTE dinámicamente
                // (el init.sql + seed garantizan que exista; además hay
                // auto-seed defensivo en `idEstado()`).
                'estado'    => $this->idEstado('PENDIENTE'),
                'vehiculo'  => (int)$d['id_vehiculo'],
                'chofer'    => (int)$d['ci_chofer'],
                'enfermero' => !empty($d['ci_enfermero']) ? (int)$d['ci_enfermero'] : null,
                'admin'     => (int)($d['ci_administrativo'] ?? 11111111),
                'paciente'  => $d['ci_paciente_externo'] ?? null,
                'tipo'      => $d['tipo'],
                'critico'   => !empty($d['estadoCritico']) ? 1 : 0,
                'camilla'   => !empty($d['requiereCamilla']) ? 1 : 0,
                'diag'      => $d['tipo_diagnostico'] ?? null,
                'jerarquia' => $d['jerarquia_enfermero'] ?? null,
                'volver'    => !empty($d['volver_origen']) ? 1 : 0,
                'prioridad' => $prioridad,
            ]);

            $sid = (int)$this->db->lastInsertId();

            // Hookpoint #131: ocupar el vehículo (NO-DISPONIBLE) dentro
            // de la misma transacción de creación del traslado.
            $this->ocuparVehiculo((int)$d['id_vehiculo'], $sid);

            $dStmt = $this->db->prepare(
                "INSERT INTO destinos_traslado
                 (id_solicitud, orden, id_ubicacion, fecha_llegada_estimada, estado_destino)
                 VALUES (:s, :o, :u, :e, 'PENDIENTE')"
            );
            $i = 1;
            foreach ($destinos as $dest) {
                $dStmt->execute([
                    's' => $sid,
                    'o' => $i,
                    'u' => (int)$dest['id'],
                    'e' => date('Y-m-d H:i:s', strtotime('+' . (45 * $i) . ' minutes')),
                ]);
                $i++;
            }
            if (!empty($d['volver_origen'])) {
                $dStmt->execute([
                    's' => $sid,
                    'o' => $i,
                    'u' => $origenId,
                    'e' => date('Y-m-d H:i:s', strtotime('+' . (45 * $i) . ' minutes')),
                ]);
            }

            $this->db->commit();
            // Auditoría de ocupación del vehículo (separada del CREAR del
            // traslado para que sea fácil filtrar por origen del evento).
            $this->registrarAuditoria(
                'OCUPAR_VEHICULO',
                'vehiculos',
                (int)$d['id_vehiculo'],
                [
                    'traslado_id' => $sid,
                    'estado_anterior' => 'DISPONIBLE',
                    'estado_nuevo'    => 'NO-DISPONIBLE',
                ],
            );
            $this->registrarAuditoria(
                'CREAR',
                'solicitud_traslados',
                $sid,
                [
                    'tipo' => $d['tipo'] ?? null,
                    'prioridad' => $prioridad,
                    'destinos' => array_column($destinos, 'id'),
                    'volver_origen' => !empty($d['volver_origen']),
                    'vehiculo_id' => (int)$d['id_vehiculo'],
                    'ci_chofer' => (int)$d['ci_chofer'],
                ],
            );
            return $sid;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function obtenerChoferesDisponibles(): array
    {
        // Filtra por `tipo_rol = 'CHOFER'` (no por id_rol hardcodeado) para
        // sobrevivir inserciones / cambios en la tabla `roles`. El listado
        // operativo del wizard NO incluye soporte_tecnico: ese rol nunca se
        // mapea a CHOFER ni a ENFERMERO.
        $sql = "SELECT DISTINCT u.ci, u.nombre, u.apellido
                FROM usuarios u
                JOIN usuario_roles ur ON u.id = ur.id_usuario
                JOIN roles r ON ur.id_rol = r.id
                WHERE r.tipo_rol = 'CHOFER' AND u.activo = TRUE
                ORDER BY u.nombre, u.apellido";
        return $this->db->query($sql)->fetchAll();
    }

    public function obtenerEnfermeros(): array
    {
        // Mismo criterio: filtramos por tipo_rol del catálogo en vez de id_rol.
        $sql = "SELECT DISTINCT u.ci, u.nombre, u.apellido
                FROM usuarios u
                JOIN usuario_roles ur ON u.id = ur.id_usuario
                JOIN roles r ON ur.id_rol = r.id
                WHERE r.tipo_rol = 'ENFERMERO' AND u.activo = TRUE
                ORDER BY u.nombre, u.apellido";
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Vehículos disponibles (no ocupados en traslados activos).
     *
     * Filtro opcional por tipo de traslado:
     *   - null: sin filtro (devuelve todos los disponibles). El JS
     *     aplica el filtro visualmente cuando el usuario elige tipo.
     *   - 'equipamiento': SOLO camiones.
     *   - cualquier otro valor explícito (paciente_alta, biologico):
     *     todos MENOS camiones.
     *
     * Importante: con `tipo = null` NO se aplica ningún filtro SQL,
     * porque el frontend usa estos datos para pintar la grilla completa
     * y luego ocultar visualmente los camiones cuando el tipo NO es
     * equipamiento (y al revés). Filtrar en SQL con `null` rompía la
     * grilla: si el usuario elegía equipamiento y los camiones estaban
     * ocupados, veía 0 vehículos en vez del fallback SAME.
     */
    public function obtenerVehiculosDisponibles(?string $tipo = null): array
    {
        $sql = "SELECT v.id, v.matricula, tv.descripcion AS tipo_vehiculo
                FROM vehiculos v
                JOIN tipo_vehiculo tv ON v.id_tipo_vehiculo = tv.id
                WHERE v.estado = 'DISPONIBLE'
                  AND v.activo = TRUE
                  AND NOT EXISTS (
                      SELECT 1 FROM solicitud_traslados st
                      JOIN estado_traslados et ON st.id_estado = et.id
                      WHERE st.id_vehiculo = v.id
                        AND et.estado IN ('PENDIENTE', 'EN_TRANSITO')
                  )";

        // Solo aplicar filtro SQL si el llamador pasa un tipo EXPLÍCITO.
        if ($tipo === 'equipamiento') {
            $sql .= " AND tv.descripcion LIKE '%Camión%'";
        } elseif ($tipo !== null) {
            $sql .= " AND tv.descripcion NOT LIKE '%Camión%'";
        }
        // $tipo === null → sin filtro extra (el JS decide visualmente).

        $sql .= " ORDER BY tv.id, v.matricula";
        return $this->db->query($sql)->fetchAll();
    }

    public function obtenerUbicaciones(): array
    {
        $sql = "SELECT id, nombre_lugar, direccion FROM ubicaciones ORDER BY nombre_lugar";
        $filas = $this->db->query($sql)->fetchAll();

        // Auto-seed defensivo: si la tabla está vacía (BD sin seed),
        // sembrar las 10 ubicaciones reales del Hospital de Clínicas.
        // Idempotente (INSERT IGNORE). Desbloquea el wizard de traslados
        // sin depender de que el seed original haya corrido.
        if (empty($filas)) {
            $this->db->exec(
                "INSERT IGNORE INTO ubicaciones (nombre_lugar, direccion) VALUES
                 ('Hospital de Clínicas - Base Ambulancias', 'Av. Italia s/n, Piso 1'),
                 ('Instituto Nacional de Cardiología', 'Av. Italia 2870'),
                 ('Hospital Maciel', '25 de Mayo 174'),
                 ('Hospital Británico', 'Av. Italia s/n'),
                 ('ASSE - Centro Hospitalario Pereira Rossell', '18 de Julio 1892'),
                 ('Médica Uruguaya', 'Constituyente 1824'),
                 ('Sanatorio Americano', 'Guido 1900'),
                 ('Hospital Pasteur', 'Monte Caseros 2629'),
                 ('Hospital Militar', 'Av. 8 de Octubre 3060'),
                 ('Centro de Salud Ciudad Vieja', 'Juncal 1395')"
            );
            $filas = $this->db->query($sql)->fetchAll();
        }

        return $filas;
    }

    /**
     * Busca una ubicación por nombre (case-insensitive, ignorando espacios).
     */
    public function buscarUbicacionPorNombre(string $nombre): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, nombre_lugar, direccion
             FROM ubicaciones
             WHERE LOWER(TRIM(nombre_lugar)) = LOWER(TRIM(:n))
             LIMIT 1"
        );
        $stmt->execute(['n' => $nombre]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    }

    /**
     * Crea una ubicación nueva. Si ya existe una con el mismo nombre,
     * devuelve la existente en lugar de duplicarla.
     */
    public function crearUbicacion(string $nombre, string $direccion): array
    {
        $nombre    = trim($nombre);
        $direccion = trim($direccion);

        $existente = $this->buscarUbicacionPorNombre($nombre);
        if ($existente !== null) {
            return [
                'id'           => (int)$existente['id'],
                'nombre_lugar' => $existente['nombre_lugar'],
                'direccion'    => $existente['direccion'],
                'existia'      => true,
            ];
        }

        $stmt = $this->db->prepare(
            "INSERT INTO ubicaciones (nombre_lugar, direccion) VALUES (:n, :d)"
        );
        $stmt->execute(['n' => $nombre, 'd' => $direccion]);
        $id = (int)$this->db->lastInsertId();

        $this->registrarAuditoria(
            'CREAR',
            'ubicaciones',
            $id,
            ['nombre_lugar' => $nombre, 'direccion' => $direccion],
        );

        return [
            'id'           => $id,
            'nombre_lugar' => $nombre,
            'direccion'    => $direccion,
            'existia'      => false,
        ];
    }

    public function registrarArribo(int $idSolicitud, int $ordenDestino, string $timestamp): array
    {
        // El cliente envía el timestamp en ISO 8601 (`YYYY-MM-DDTHH:MM:SS.sssZ`)
        // pero MySQL DATETIME requiere `YYYY-MM-DD HH:MM:SS`. Convertimos acá
        // para mantener un contrato de API limpio (ISO) sin filtrar formatos
        // específicos de motor al frontend.
        $tsMysql = $this->normalizarTimestampMysql($timestamp);

        $stmt = $this->db->prepare(
            "UPDATE destinos_traslado
             SET estado_destino = 'ARRIBADO',
                 fecha_llegada_efectiva = :ts
             WHERE id_solicitud = :s AND orden = :o AND estado_destino <> 'ARRIBADO'"
        );
        $stmt->execute([
            'ts' => $tsMysql,
            's'  => $idSolicitud,
            'o'  => $ordenDestino,
        ]);
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Destino ya arribado o inexistente'];
        }
        $this->avanzarEstadoPadre($idSolicitud);
        $this->registrarAuditoria(
            'ACTUALIZAR',
            'destinos_traslado',
            $idSolicitud,
            ['accion' => 'arribo', 'destino_orden' => $ordenDestino, 'timestamp' => $tsMysql],
        );
        return ['success' => true];
    }

    /**
     * Normaliza un timestamp recibido por la API a formato MySQL DATETIME.
     * Acepta:
     *   - ISO 8601 con Z o offset (ej. "2026-08-02T17:21:35.579Z")
     *   - "YYYY-MM-DD HH:MM:SS"
     *   - "YYYY-MM-DD HH:MM:SS.uuuuuu"
     * Devuelve el string en formato "Y-m-d H:i:s" listo para bind en PDO.
     */
    private function normalizarTimestampMysql(string $timestamp): string
    {
        // Si ya viene en formato MySQL-ish, normalizar longitud sin microsegundos.
        if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/', $timestamp)) {
            $dt = \DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                str_replace('T', ' ', substr($timestamp, 0, 19)),
            );
            if ($dt instanceof \DateTimeImmutable) {
                return $dt->format('Y-m-d H:i:s');
            }
        }
        // Fallback: intentar parsear como datetime genérico (ISO 8601 con Z/offset).
        try {
            $dt = new \DateTimeImmutable($timestamp);
            return $dt->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            // Último recurso: NOW() del servidor. Logueamos el problema.
            error_log('normalizarTimestampMysql: timestamp no parseable: ' . $timestamp);
            return date('Y-m-d H:i:s');
        }
    }

    /**
     * Registra la salida hacia un destino. Marca el destino como EN_TRANSITO
     * y, si la solicitud estaba PENDIENTE, la promueve a EN_TRANSITO.
     */
    public function registrarSalida(int $idSolicitud, int $ordenDestino): array
    {
        $stmt = $this->db->prepare(
            "UPDATE destinos_traslado
             SET estado_destino = 'EN_TRANSITO'
             WHERE id_solicitud = :s AND orden = :o AND estado_destino = 'PENDIENTE'"
        );
        $stmt->execute(['s' => $idSolicitud, 'o' => $ordenDestino]);
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Destino no está pendiente'];
        }
        // Promover la solicitud a EN_TRANSITO si estaba PENDIENTE
        $estadoEnTransito = $this->idEstado('EN_TRANSITO');
        $estadoPendiente  = $this->idEstado('PENDIENTE');
        $u = $this->db->prepare(
            "UPDATE solicitud_traslados
             SET id_estado = :transito
             WHERE id = :id AND id_estado = :pendiente"
        );
        $u->execute([
            'transito'  => $estadoEnTransito,
            'id'        => $idSolicitud,
            'pendiente' => $estadoPendiente,
        ]);
        $this->registrarAuditoria(
            'ACTUALIZAR',
            'destinos_traslado',
            $idSolicitud,
            ['accion' => 'salida', 'destino_orden' => $ordenDestino],
        );
        return ['success' => true];
    }

    public function crearReporte(int $idSolicitud, int $ordenDestino, string $tipo, string $mensaje): array
    {
        $s = $this->db->prepare(
            "SELECT id FROM destinos_traslado WHERE id_solicitud = :s AND orden = :o"
        );
        $s->execute(['s' => $idSolicitud, 'o' => $ordenDestino]);
        $row = $s->fetch();
        if (!$row) {
            return ['success' => false, 'message' => 'Destino no encontrado'];
        }
        $stmt = $this->db->prepare(
            "INSERT INTO reportes_destino (id_destino, tipo_problema, mensaje)
             VALUES (:d, :t, :m)"
        );
        $stmt->execute([
            'd' => $row['id'],
            't' => $tipo,
            'm' => $mensaje,
        ]);
        $reporteId = (int)$this->db->lastInsertId();
        $this->registrarAuditoria(
            'CREAR',
            'reportes_destino',
            $reporteId,
            [
                'id_solicitud' => $idSolicitud,
                'destino_orden' => $ordenDestino,
                'tipo_problema' => $tipo,
            ],
        );
        return ['success' => true, 'id' => $reporteId];
    }

    public function cancelar(int $idSolicitud, int $ordenDestino, string $tipo, string $mensaje): array
    {
        $this->db->beginTransaction();
        try {
            $estadoCancelado = $this->idEstado('CANCELADO');
            $estadoFinalizado = $this->idEstado('FINALIZADO');
            $u = $this->db->prepare(
                "UPDATE solicitud_traslados
                 SET id_estado = :e,
                     fecha_hora_llegada_efectiva = NOW()
                 WHERE id = :id AND id_estado <> :finalizado"
            );
            $u->execute([
                'e'         => $estadoCancelado,
                'id'        => $idSolicitud,
                'finalizado'=> $estadoFinalizado,
            ]);
            if ($u->rowCount() === 0) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'No se puede cancelar un traslado finalizado'];
            }
            // Hookpoint #131: liberar vehículo dentro de la misma tx
            // que la cancelación (atómico con el cambio de estado).
            $this->liberarVehiculoPorSolicitud($idSolicitud, $estadoCancelado, 'cancelar');
            $this->crearReporte($idSolicitud, $ordenDestino, $tipo, $mensaje);
            $this->db->commit();
            $this->registrarAuditoria(
                'ACTUALIZAR',
                'solicitud_traslados',
                $idSolicitud,
                [
                    'accion' => 'cancelar',
                    'motivo_tipo' => $tipo,
                    'motivo_mensaje' => $mensaje,
                    'destino_orden' => $ordenDestino,
                ],
            );
            return ['success' => true];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function idEstado(string $nombre): int
    {
        $s = $this->db->prepare("SELECT id FROM estado_traslados WHERE estado = :n");
        $s->execute(['n' => $nombre]);
        $fila = $s->fetch();

        if ($fila) {
            return (int)$fila['id'];
        }

        // Auto-seed defensivo: si la tabla está vacía o no tiene el
        // estado pedido, sembrar los 4 estados básicos. Idempotente
        // (INSERT IGNORE). Después volver a buscar.
        $this->db->exec(
            "INSERT IGNORE INTO estado_traslados (estado) VALUES
             ('PENDIENTE'), ('EN_TRANSITO'), ('FINALIZADO'), ('CANCELADO')"
        );
        $s->execute(['n' => $nombre]);
        $fila = $s->fetch();
        if (!$fila) {
            throw new \RuntimeException(
                "Estado de traslado '{$nombre}' no existe en estado_traslados y no pudo sembrarse."
            );
        }
        return (int)$fila['id'];
    }

    private function avanzarEstadoPadre(int $idSolicitud): void
    {
        $tot = $this->db->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(estado_destino = 'ARRIBADO') AS ok
             FROM destinos_traslado WHERE id_solicitud = :s"
        );
        $tot->execute(['s' => $idSolicitud]);
        $r = $tot->fetch();
        $estadoId = ((int)$r['total'] === (int)$r['ok'])
            ? $this->idEstado('FINALIZADO')
            : $this->idEstado('EN_TRANSITO');

        $u = $this->db->prepare(
            "UPDATE solicitud_traslados
             SET id_estado = :e,
                 fecha_hora_llegada_efectiva = NOW()
             WHERE id = :id"
        );
        $u->execute(['e' => $estadoId, 'id' => $idSolicitud]);

        // Hookpoint #131: si el padre pasó a FINALIZADO, liberar el
        // vehículo. Se hace autocontenido (sin abrir tx nueva) porque
        // este helper NO está dentro de una transacción; el filtro
        // `obtenerVehiculosDisponibles()` ya compensa con `NOT EXISTS`.
        if ($estadoId === $this->idEstado('FINALIZADO')) {
            $this->liberarVehiculoPorSolicitud($idSolicitud, $estadoId, 'finalizar');
        }
    }

    private function calcularPasoInfo(array $traslado, array $destinos): ?array
    {
        // Traslados terminales no tienen acciones pendientes.
        $estadoNombre = strtoupper((string)($traslado['estado_nombre'] ?? ''));
        if ($estadoNombre === 'CANCELADO' || $estadoNombre === 'FINALIZADO') {
            return null;
        }
        if (empty($destinos)) {
            return null;
        }

        // Defensa: el query ya ordena por `orden`, pero esta función es
        // crítica para toda la UI; no se debe depender del orden de llegada.
        $ordenados = $destinos;
        usort($ordenados, fn($a, $b) => ((int)$a['orden']) <=> ((int)$b['orden']));

        foreach ($ordenados as $d) {
            $estado = strtoupper((string)($d['estado_destino'] ?? 'PENDIENTE'));
            if ($estado === 'ARRIBADO') {
                continue;
            }
            $esRetorno = (bool)($d['es_retorno'] ?? false);
            $info = [
                'destino_orden' => (int)$d['orden'],
                'destino_id' => (int)$d['id'],
                'destino_nombre' => (string)($d['nombre'] ?? ''),
                'es_retorno' => $esRetorno,
            ];
            if ($estado === 'PENDIENTE') {
                $info['tipo'] = $esRetorno ? 'inicio_retorno_central' : 'inicio_traslado';
            } else {
                // EN_TRANSITO: esperando confirmación de llegada.
                $info['tipo'] = $esRetorno ? 'registrar_llegada_central' : 'registrar_llegada';
            }
            return $info;
        }

        // Todos los destinos arribados: traslado listo para finalizar.
        return null;
    }

    /**
     * Hookpoint #131 — ocupa un vehículo (`NO-DISPONIBLE`) al crear un
     * traslado. Se invoca dentro de la transacción de `crearSolicitud()`
     * para que la indisponibilidad sea atómica con la creación del
     * traslado. Si el vehículo no existe o ya estaba `NO-DISPONIBLE`,
     * la query no aplica cambios pero NO lanza — el filtro del wizard
     * compensa vía `NOT EXISTS` sobre traslados activos.
     */
    private function ocuparVehiculo(int $idVehiculo, int $idTraslado): void
    {
        $stmt = $this->db->prepare(
            "UPDATE vehiculos
             SET estado = 'NO-DISPONIBLE'
             WHERE id = :id AND activo = TRUE"
        );
        $stmt->execute(['id' => $idVehiculo]);
        // No logueamos acá: el caller ya emite 'OCUPAR_VEHICULO' en
        // logs_auditoria fuera de la tx, con el contexto del traslado.
    }

    /**
     * Hookpoint #131 — libera el vehículo asociado a un traslado que
     * acaba de pasar a un estado terminal (`FINALIZADO` o `CANCELADO`).
     * Usa JOIN para resolver `id_vehiculo` sin un SELECT previo.
     *
     * Si el vehículo ya estaba `DISPONIBLE` (caso borde por
     * inconsistencia previa), loguea `INCONSISTENCIA_VEHICULO` y
     * continúa best-effort.
     *
     * @param int $idEstadoEsperado id en `estado_traslados` del estado
     *                              terminal al que acaba de pasar el
     *                              traslado (FINALIZADO o CANCELADO).
     * @param string $motivo texto para `logs_auditoria.detalle` —
     *                       típicamente 'finalizar' o 'cancelar'.
     */
    private function liberarVehiculoPorSolicitud(
        int $idSolicitud,
        int $idEstadoEsperado,
        string $motivo
    ): void {
        $stmt = $this->db->prepare(
            "UPDATE vehiculos v
             JOIN solicitud_traslados s ON s.id_vehiculo = v.id
             SET v.estado = 'DISPONIBLE'
             WHERE s.id = :id AND s.id_estado = :estado AND v.activo = TRUE"
        );
        $stmt->execute([
            'id'     => $idSolicitud,
            'estado' => $idEstadoEsperado,
        ]);

        if ($stmt->rowCount() === 0) {
            // No updateó nada: el vehículo ya estaba DISPONIBLE o la
            // solicitud no quedó en el estado esperado. Logueamos la
            // inconsistencia pero NO rompemos la transición.
            $this->registrarAuditoria(
                'INCONSISTENCIA_VEHICULO',
                'vehiculos',
                0,
                [
                    'traslado_id' => $idSolicitud,
                    'motivo'      => 'liberar_sin_cambios',
                    'contexto'    => $motivo,
                ],
            );
            return;
        }

        $this->registrarAuditoria(
            'LIBERAR_VEHICULO',
            'vehiculos',
            0,
            [
                'traslado_id'      => $idSolicitud,
                'estado_anterior'  => 'NO-DISPONIBLE',
                'estado_nuevo'     => 'DISPONIBLE',
                'motivo'           => $motivo,
            ],
        );
    }

    private function registrarAuditoria(string $accion, string $tabla, int $registroId, array $detalles): void
    {
        try {
            $sql = "INSERT INTO logs_auditoria
                    (id_usuario, accion, tabla_afectada, registro_id, detalles, ip_origen, fecha_hora)
                    VALUES (:u, :a, :t, :r, :d, :ip, NOW())";
            $stmt = $this->db->prepare($sql);
            $user = Sesion::obtener('user');
            $stmt->execute([
                'u'  => $user['id'] ?? null,
                'a'  => $accion,
                't'  => $tabla,
                'r'  => $registroId,
                'd'  => json_encode($detalles, JSON_UNESCAPED_UNICODE),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable $e) {
            // La auditoría no debe romper el flujo principal
            error_log('Auditoria fallo: ' . $e->getMessage());
        }
    }
}