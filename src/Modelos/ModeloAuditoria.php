<?php
namespace Modelos;

use Nucleo\Conexion;
use PDO;

class ModeloAuditoria {
    private $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenerInstancia();
    }

    public function obtenerLogs($limite = 100) {
        $sql = "SELECT l.*, u.nombre as nombre_usuario 
                FROM logs_auditoria l
                LEFT JOIN usuarios u ON l.id_usuario = u.id 
                ORDER BY l.id DESC 
                LIMIT :limite";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function registrar($accion, $tabla_afectada, $detalles, $idUsuario = null) {
        try {
            $ip_origen = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
            
            // LA MAGIA: Convertimos el texto a JSON para que MySQL lo acepte
            $detalles_json = json_encode(['Mensaje' => $detalles], JSON_UNESCAPED_UNICODE);
            
            $sql = "INSERT INTO logs_auditoria (id_usuario, accion, tabla_afectada, detalles, ip_origen, fecha_hora) 
                    VALUES (:id_usuario, :accion, :tabla_afectada, :detalles, :ip_origen, NOW())";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
            $stmt->bindParam(':accion', $accion, PDO::PARAM_STR);
            $stmt->bindParam(':tabla_afectada', $tabla_afectada, PDO::PARAM_STR);
            $stmt->bindParam(':detalles', $detalles_json, PDO::PARAM_STR);
            $stmt->bindParam(':ip_origen', $ip_origen, PDO::PARAM_STR);
            
            return $stmt->execute();
        } catch (\PDOException $e) {
            error_log("Error al registrar auditoría: " . $e->getMessage());
            return false;
        }
    }
}