<?php

namespace Modelos;

use Nucleo\Conexion;
use PDO;

class ModeloTraslado
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexion::obtenerInstancia();
    }

    public function obtenerTodosActivos(): array
    {
        $sql = "SELECT st.*, 
                       u1.nombre_lugar AS origen, 
                       u2.nombre_lugar AS destino,
                       et.estado AS estado_nombre,
                       tv.descripcion AS tipo_vehiculo,
                       CONCAT(u_chofer.nombre, ' ', u_chofer.apellido) AS chofer_nombre
                FROM solicitud_traslados st
                JOIN ubicaciones u1 ON st.id_ubicacion_origen = u1.id
                JOIN ubicaciones u2 ON st.id_ubicacion_destino = u2.id
                JOIN estado_traslados et ON st.id_estado = et.id
                JOIN vehiculos v ON st.id_vehiculo = v.id
                JOIN tipo_vehiculo tv ON v.id_tipo_vehiculo = tv.id
                JOIN usuarios u_chofer ON st.ci_chofer = u_chofer.ci
                ORDER BY st.fecha_hora_salida DESC";
        return $this->db->query($sql)->fetchAll();
    }

    public function obtenerPorId(int $id): ?array
    {
        $sql = "SELECT st.*, 
                       u1.nombre_lugar AS origen, 
                       u2.nombre_lugar AS destino,
                       et.estado AS estado_nombre,
                       v.matricula,
                       tv.descripcion AS tipo_vehiculo,
                       CONCAT(u_chofer.nombre, ' ', u_chofer.apellido) AS chofer_nombre,
                       CONCAT(u_enf.nombre, ' ', u_enf.apellido) AS enfermero_nombre
                FROM solicitud_traslados st
                JOIN ubicaciones u1 ON st.id_ubicacion_origen = u1.id
                JOIN ubicaciones u2 ON st.id_ubicacion_destino = u2.id
                JOIN estado_traslados et ON st.id_estado = et.id
                JOIN vehiculos v ON st.id_vehiculo = v.id
                JOIN tipo_vehiculo tv ON v.id_tipo_vehiculo = tv.id
                JOIN usuarios u_chofer ON st.ci_chofer = u_chofer.ci
                LEFT JOIN usuarios u_enf ON st.ci_enfermero = u_enf.ci
                WHERE st.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function crearSolicitud(array $datos): bool
    {
        $sql = "INSERT INTO solicitud_traslados (
                    id_ubicacion_origen, id_ubicacion_destino, fecha_hora_salida, 
                    fecha_hora_llegada_estimada, id_estado, id_vehiculo, 
                    ci_chofer, ci_enfermero, ci_administrativo, ci_paciente_externo
                ) VALUES (
                    :origen, :destino, :salida, :estimada, :estado, :vehiculo, 
                    :chofer, :enfermero, :admin, :paciente
                )";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'origen'    => $datos['id_ubicacion_origen'],
            'destino'   => $datos['id_ubicacion_destino'],
            'salida'    => $datos['fecha_hora_salida'],
            'estimada'  => $datos['fecha_hora_llegada_estimada'],
            'estado'    => $datos['id_estado'] ?? 1, // 1 = PENDIENTE
            'vehiculo'  => $datos['id_vehiculo'],
            'chofer'    => $datos['ci_chofer'],
            'enfermero' => $datos['ci_enfermero'] ?? null,
            'admin'     => $datos['ci_administrativo'],
            'paciente'  => $datos['ci_paciente_externo'] ?? null
        ]);
    }

    public function obtenerChoferesDisponibles(): array
    {
        $sql = "SELECT DISTINCT u.ci, u.nombre, u.apellido 
                FROM usuarios u 
                JOIN usuario_roles ur ON u.id = ur.id_usuario 
                WHERE ur.id_rol = 3 AND u.activo = TRUE";
        return $this->db->query($sql)->fetchAll();
    }

    public function obtenerEnfermeros(): array
    {
        $sql = "SELECT DISTINCT u.ci, u.nombre, u.apellido 
                FROM usuarios u 
                JOIN usuario_roles ur ON u.id = ur.id_usuario 
                WHERE ur.id_rol = 4 AND u.activo = TRUE";
        return $this->db->query($sql)->fetchAll();
    }

    public function obtenerVehiculosDisponibles(): array
    {
        $sql = "SELECT v.id, v.matricula, tv.descripcion AS tipo_vehiculo 
                FROM vehiculos v 
                JOIN tipo_vehiculo tv ON v.id_tipo_vehiculo = tv.id 
                WHERE v.estado = 'DISPONIBLE'";
        return $this->db->query($sql)->fetchAll();
    }

    public function obtenerUbicaciones(): array
    {
        $sql = "SELECT id, nombre_lugar, direccion FROM ubicaciones";
        return $this->db->query($sql)->fetchAll();
    }
}
