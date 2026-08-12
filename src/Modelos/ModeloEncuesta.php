<?php
namespace Modelos;

use Nucleo\Conexion;

class ModeloEncuesta {
    private $db;

    public function __construct() {
        $this->db = Conexion::obtenerInstancia();
    }

    public function guardarRespuestas(array $datos): bool {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("INSERT INTO respuestas_encuesta (id_encuesta, ci_usuario, calificacion) VALUES (?, ?, ?)");
            $stmt->execute([$datos['id_encuesta'], $datos['ci_usuario'], $datos['calificacion']]);
            $idRespuesta = $this->db->lastInsertId();

            $stmtDetalle = $this->db->prepare("INSERT INTO respuestas_encuesta_detalle (id_respuesta_encuesta, numero_pregunta, respuesta_valor) VALUES (?, ?, ?)");
            foreach ($datos['respuestas_detalle'] as $numeroPregunta => $valor) {
                $stmtDetalle->execute([$idRespuesta, $numeroPregunta, $valor]);
            }

            // Auditoría solo si no es anónima
            if ($datos['ci_usuario'] !== null) {
                $idUsuarioAuditoria = $_SESSION['user']['id'] ?? null;
                $stmtAudit = $this->db->prepare("INSERT INTO logs_auditoria (id_usuario, accion, tabla_afectada, registro_id) VALUES (?, 'CREAR', 'respuestas_encuesta', ?)");
                $stmtAudit->execute([$idUsuarioAuditoria, $idRespuesta]);
            }

            $this->db->commit();
            return true;
        } catch (\PDOException $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function obtenerPorId(int $id) {
        $stmt = $this->db->prepare("SELECT * FROM encuestas WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function obtenerPorToken(string $token) {
        $stmt = $this->db->prepare("SELECT * FROM encuestas WHERE token_publico = ?");
        $stmt->execute([$token]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}