<?php

namespace Modelos;

use Nucleo\Conexion;
use PDO;
use Exception;

class ModeloDocumento
{
    private $conexion;

    public function __construct()
    {
        $this->conexion = Conexion::obtenerInstancia();
    }

    public function obtenerCategorias()
    {
        $sql = "SELECT id, nombre_categoria, slug FROM categorias_documentos ORDER BY nombre_categoria ASC";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerTodos()
    {
        $sql = "SELECT d.*, c.nombre_categoria, c.slug 
            FROM documentos d 
            LEFT JOIN categorias_documentos c ON d.id_categoria = c.id 
            ORDER BY d.created_at DESC";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->conexion->prepare("SELECT d.*, c.nombre_categoria FROM documentos d JOIN categorias_documentos c ON d.id_categoria = c.id WHERE d.id = :id");
        $stmt->execute(['id' => $id]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function obtenerPorSlugCategoria(string $slug): array
    {
        $sql = "SELECT d.*, c.nombre_categoria, c.slug 
                FROM documentos d 
                INNER JOIN categorias_documentos c ON d.id_categoria = c.id 
                WHERE c.slug = :slug AND d.documento_activo = 1
                ORDER BY d.created_at DESC";

        $stmt = $this->conexion->prepare($sql);
        $stmt->execute(['slug' => $slug]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear(array $datos): bool
    {
        $sql = "INSERT INTO documentos (id_categoria, titulo, ruta_archivo, documento_activo, ci_funcionario) 
                VALUES (:id_categoria, :titulo, :ruta_archivo, :documento_activo, :ci_funcionario)";
        $stmt = $this->conexion->prepare($sql);
        return $stmt->execute([
            'id_categoria'     => $datos['id_categoria'],
            'titulo'           => $datos['titulo'],
            'ruta_archivo'     => $datos['ruta_archivo'],
            'documento_activo' => $datos['documento_activo'] ?? 1,
            'ci_funcionario'   => $datos['ci_funcionario']
        ]);
    }

    /**
     * Busca el ID numérico de la categoría recibiendo un ID o un slug.
     */
    public function obtenerIdCategoria($categoria): int
    {
        if (is_numeric($categoria) && (int)$categoria > 0) {
            return (int)$categoria;
        }

        if (is_string($categoria) && !empty($categoria)) {
            $stmt = $this->conexion->prepare("SELECT id FROM categorias_documentos WHERE slug = :slug LIMIT 1");
            $stmt->execute(['slug' => $categoria]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res && isset($res['id'])) {
                return (int)$res['id'];
            }
        }

        // Si no se encuentra la categoría o viene vacía, asigna la primera categoría por defecto (ID: 1)
        return 1;
    }

    public function subirArchivo(array $file, array $meta): int
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Error al subir el archivo (Código: " . $file['error'] . ")");
        }

        if ($file['type'] !== 'application/pdf') {
            throw new Exception("El archivo debe ser un PDF.");
        }

        $nombreOriginal = pathinfo($file['name'], PATHINFO_FILENAME);
        $nombreSanitizado = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nombreOriginal);
        $nombreUnico = uniqid($nombreSanitizado . '_') . '.pdf';

        $directorioDestino = __DIR__ . '/../uploads/';

        if (!is_dir($directorioDestino)) {
            mkdir($directorioDestino, 0755, true);
        }

        $rutaFisica = $directorioDestino . $nombreUnico;

        if (!move_uploaded_file($file['tmp_name'], $rutaFisica)) {
            throw new Exception("No se pudo guardar el archivo en el servidor.");
        }

        $rutaWeb = '/uploads/' . $nombreUnico;

        // Convertir el slug o ID recibido al ID numérico de la BD
        $idCategoria = $this->obtenerIdCategoria($meta['id_categoria'] ?? null);

        $datosBD = [
            'id_categoria'     => $idCategoria,
            'titulo'           => $meta['titulo'] ?: $file['name'],
            'ruta_archivo'     => $rutaWeb,
            'documento_activo' => 1,
            'ci_funcionario'   => $meta['ci_funcionario'] // CI real del usuario en sesión
        ];

        if (!$this->crear($datosBD)) {
            unlink($rutaFisica);
            throw new Exception("Error al guardar el registro en la base de datos.");
        }

        return (int) $this->conexion->lastInsertId();
    }
}
