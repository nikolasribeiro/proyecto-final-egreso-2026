<?php

namespace Modelos;

use Nucleo\Conexion;
use PDO;

class ModeloDocumento {
    private PDO $db;

    public function __construct() {
        $this->db = Conexion::obtenerInstancia();
    }

    public function obtenerCategorias(): array {
        $stmt = $this->db->query("SELECT * FROM categorias_documentos ORDER BY nombre_categoria ASC");
        return $stmt->fetchAll();
    }

    public function obtenerTodos(): array {
        $sql = "SELECT d.*, c.nombre_categoria, CONCAT(u.nombre, ' ', u.apellido) AS funcionario
                FROM documentos d
                JOIN categorias_documentos c ON d.id_categoria = c.id
                JOIN usuarios u ON d.ci_funcionario = u.ci
                WHERE d.documento_activo = TRUE
                ORDER BY d.created_at DESC";
        return $this->db->query($sql)->fetchAll();
    }

    public function obtenerPorId(int $id): ?array {
        $stmt = $this->db->prepare("SELECT d.*, c.nombre_categoria FROM documentos d JOIN categorias_documentos c ON d.id_categoria = c.id WHERE d.id = :id");
        $stmt->execute(['id' => $id]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function crear(array $datos): bool {
        $sql = "INSERT INTO documentos (id_categoria, titulo, ruta_archivo, documento_activo, ci_funcionario) 
                VALUES (:id_categoria, :titulo, :ruta_archivo, :documento_activo, :ci_funcionario)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id_categoria'     => $datos['id_categoria'],
            'titulo'           => $datos['titulo'],
            'ruta_archivo'     => $datos['ruta_archivo'],
            'documento_activo' => $datos['documento_activo'] ?? true,
            'ci_funcionario'   => $datos['ci_funcionario']
        ]);
    }
}