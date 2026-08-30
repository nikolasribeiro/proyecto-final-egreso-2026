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

   // Obtenemos encuestas activas y cruzamos con su categoría
    public function obtenerTodas() {
        $sql = "SELECT e.*, c.nombre_categoria 
                FROM encuestas e 
                LEFT JOIN categorias_documentos c ON e.id_categoria = c.id 
                WHERE e.activa = 1 
                ORDER BY e.id DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // Obtenemos categorías para llenar el desplegable
    public function obtenerCategorias() {
        $stmt = $this->db->query("SELECT * FROM categorias_documentos ORDER BY nombre_categoria ASC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

  public function crear(array $datos) {
        $stmt = $this->db->prepare("INSERT INTO encuestas (segmento_dirigido, es_anonima, token_publico, fecha_vencimiento, id_categoria, id_plantilla, preguntas) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        return $stmt->execute([
            $datos['segmento_dirigido'], 
            $datos['es_anonima'], 
            $datos['token_publico'], 
            $datos['fecha_vencimiento'],
            $datos['id_categoria'],
            $datos['id_plantilla'], 
            $datos['preguntas'] 
        ]);
    }

    // BAJA LÓGICA: En lugar de DELETE, apagamos el registro
    public function eliminar(int $id) {
        $stmt = $this->db->prepare("UPDATE encuestas SET activa = 0 WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function obtenerPorSegmento(string $segmento) {
        $stmt = $this->db->prepare("SELECT * FROM encuestas WHERE activa = 1 AND segmento_dirigido = ? ORDER BY id DESC");
        $stmt->execute([$segmento]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

   public function obtenerResultados(int $idEncuesta) {
        // 1. Totales
        $stmtTotal = $this->db->prepare("SELECT COUNT(id) as total, AVG(calificacion) as promedio_general FROM respuestas_encuesta WHERE id_encuesta = ?");
        $stmtTotal->execute([$idEncuesta]);
        $totales = $stmtTotal->fetch(\PDO::FETCH_ASSOC);

        // 2. Promedios por pregunta
        $stmtDetalles = $this->db->prepare("
            SELECT red.numero_pregunta, AVG(red.respuesta_valor) as promedio 
            FROM respuestas_encuesta_detalle red
            JOIN respuestas_encuesta re ON red.id_respuesta_encuesta = re.id
            WHERE re.id_encuesta = ?
            GROUP BY red.numero_pregunta
            ORDER BY red.numero_pregunta ASC
        ");
        $stmtDetalles->execute([$idEncuesta]);
        $promediosPorPregunta = $stmtDetalles->fetchAll(\PDO::FETCH_ASSOC);

        // 3. NUEVO: Distribución exacta de votos
        $stmtDist = $this->db->prepare("
            SELECT red.numero_pregunta, red.respuesta_valor, COUNT(*) as cantidad 
            FROM respuestas_encuesta_detalle red
            JOIN respuestas_encuesta re ON red.id_respuesta_encuesta = re.id
            WHERE re.id_encuesta = ?
            GROUP BY red.numero_pregunta, red.respuesta_valor
        ");
        $stmtDist->execute([$idEncuesta]);
        $distribucionRaw = $stmtDist->fetchAll(\PDO::FETCH_ASSOC);

        // Agrupamos la distribución para usarla fácil en la vista
        $distribucion = [];
        foreach ($distribucionRaw as $fila) {
            $distribucion[$fila['numero_pregunta']][$fila['respuesta_valor']] = $fila['cantidad'];
        }

        return [
            'total_respuestas' => (int)$totales['total'],
            'promedio_general' => round((float)$totales['promedio_general'], 1),
            'detalles' => $promediosPorPregunta,
            'distribucion' => $distribucion // Enviamos el detalle
        ];
    }
}