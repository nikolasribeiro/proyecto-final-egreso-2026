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
     * Devuelve todas las solicitudes activas (PENDIENTE / EN_TRANSITO) con
     * resumen de destinos concatenados y campos clínicos/prioridad.
     */
    public function obtenerTodosActivos(): array
    {
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
                WHERE et.estado IN ('PENDIENTE', 'EN_TRANSITO')
                ORDER BY st.fecha_hora_salida DESC";

        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Devuelve una solicitud con sus destinos y reportes anidados.
     * Calcula paso_actual según el estado de cada destino.
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

        $traslado['destinos'] = $destinos;
        $traslado['reportes'] = $reportes;
        $traslado['paso_actual'] = $this->calcularPasoActual($traslado, $destinos);

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
                        1, :vehiculo,
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
        $sql = "SELECT DISTINCT u.ci, u.nombre, u.apellido
                FROM usuarios u
                JOIN usuario_roles ur ON u.id = ur.id_usuario
                WHERE ur.id_rol = 3 AND u.activo = TRUE
                ORDER BY u.nombre, u.apellido";
        return $this->db->query($sql)->fetchAll();
    }

    public function obtenerEnfermeros(): array
    {
        $sql = "SELECT DISTINCT u.ci, u.nombre, u.apellido
                FROM usuarios u
                JOIN usuario_roles ur ON u.id = ur.id_usuario
                WHERE ur.id_rol = 4 AND u.activo = TRUE
                ORDER BY u.nombre, u.apellido";
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Vehículos disponibles (no ocupados en traslados activos).
     * Devuelve TODOS los disponibles, incluyendo el camión. La capa de UI
     * (JS) se encarga de ocultar el camión cuando el tipo de traslado no
     * es "equipamiento". El servidor valida la compatibilidad en
     * crearSolicitud().
     */
    public function obtenerVehiculosDisponibles(): array
    {
        $sql = "SELECT v.id, v.matricula, tv.descripcion AS tipo_vehiculo
                FROM vehiculos v
                JOIN tipo_vehiculo tv ON v.id_tipo_vehiculo = tv.id
                WHERE v.estado = 'DISPONIBLE'
                  AND NOT EXISTS (
                      SELECT 1 FROM solicitud_traslados st
                      JOIN estado_traslados et ON st.id_estado = et.id
                      WHERE st.id_vehiculo = v.id
                        AND et.estado IN ('PENDIENTE', 'EN_TRANSITO')
                  )
                ORDER BY tv.id, v.matricula";
        return $this->db->query($sql)->fetchAll();
    }

    public function obtenerUbicaciones(): array
    {
        $sql = "SELECT id, nombre_lugar, direccion FROM ubicaciones ORDER BY nombre_lugar";
        return $this->db->query($sql)->fetchAll();
    }

    public function registrarArribo(int $idSolicitud, int $ordenDestino, string $timestamp): array
    {
        $stmt = $this->db->prepare(
            "UPDATE destinos_traslado
             SET estado_destino = 'ARRIBADO',
                 fecha_llegada_efectiva = :ts
             WHERE id_solicitud = :s AND orden = :o AND estado_destino <> 'ARRIBADO'"
        );
        $stmt->execute([
            'ts' => $timestamp,
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
            ['accion' => 'arribo', 'destino_orden' => $ordenDestino, 'timestamp' => $timestamp],
        );
        return ['success' => true];
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
        return (int)$s->fetch()['id'];
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
    }

    private function calcularPasoActual(array $traslado, array $destinos): int
    {
        // Cada destino aporta 2 pasos (EN_TRANSITO + ARRIBADO).
        // Si volver_al_origen=true y todos los destinos llegaron, suma 2 más (regreso).
        if (empty($destinos)) {
            return 1;
        }
        $paso = 1;
        foreach ($destinos as $d) {
            if ($d['estado_destino'] === 'ARRIBADO') {
                $paso += 2;
            } else {
                return $paso; // próximo destino pendiente
            }
        }
        if (!empty($traslado['volver_al_origen'])) {
            // Hay un destino adicional de regreso. Considerar si ya arribó.
            $ultimo = end($destinos);
            if ($ultimo && $ultimo['estado_destino'] === 'ARRIBADO') {
                $paso += 2;
            }
        }
        return $paso;
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