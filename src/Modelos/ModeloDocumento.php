<?php

namespace Modelos;

use Nucleo\Conexion;
use PDO;

class ModeloDocumento {
    private $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenerInstancia();
    }

    public function obtenerCategorias() {
    // Agrega 'slug' a la consulta
    $sql = "SELECT id, nombre_categoria, slug FROM categorias_documentos ORDER BY nombre_categoria ASC";
    $stmt = $this->conexion->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

   public function obtenerTodos() {
    // Asegúrate de traer 'c.slug' en el JOIN
    $sql = "SELECT d.*, c.nombre_categoria, c.slug 
            FROM documentos d 
            LEFT JOIN categorias_documentos c ON d.id_categoria = c.id 
            ORDER BY d.created_at DESC";
    $stmt = $this->conexion->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    public function obtenerPorId(int $id): ?array {
        $stmt = $this->conexion->prepare("SELECT d.*, c.nombre_categoria FROM documentos d JOIN categorias_documentos c ON d.id_categoria = c.id WHERE d.id = :id");
        $stmt->execute(['id' => $id]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function crear(array $datos): bool {
        $sql = "INSERT INTO documentos (id_categoria, titulo, ruta_archivo, documento_activo, ci_funcionario) 
                VALUES (:id_categoria, :titulo, :ruta_archivo, :documento_activo, :ci_funcionario)";
        $stmt = $this->conexion->prepare($sql);
        return $stmt->execute([
            'id_categoria'     => $datos['id_categoria'],
            'titulo'           => $datos['titulo'],
            'ruta_archivo'     => $datos['ruta_archivo'],
            'documento_activo' => $datos['documento_activo'] ?? true,
            'ci_funcionario'   => $datos['ci_funcionario']
        ]);
    }
}