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
                ORDER BY l.fecha_hora DESC 
                LIMIT :limite";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}