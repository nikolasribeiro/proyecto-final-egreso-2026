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
    /**
     * Procesa la subida de un archivo PDF y persiste el registro.
     * 
     * @param array $file El array $_FILES['documento']
     * @param array $meta Datos adicionales (id_categoria, titulo, ci_funcionario)
     * @return int El ID del documento insertado (o el de la categoría/dummy por simplicidad)
     * @throws Exception Si la validación o subida falla
     */
    public function subirArchivo(array $file, array $meta): int {
        // 1. Validaciones básicas
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception("Error al subir el archivo (Código: " . $file['error'] . ")");
        }

        if ($file['type'] !== 'application/pdf') {
            throw new \Exception("El archivo debe ser un PDF.");
        }

        // 2. Sanitizar el nombre del archivo y generar uno único # 116
        $nombreOriginal = pathinfo($file['name'], PATHINFO_FILENAME);
        $nombreSanitizado = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nombreOriginal);
        $nombreUnico = uniqid($nombreSanitizado . '_') . '.pdf';

        // 3. Definir la ruta física de destino (dentro del contenedor Docker)
        $directorioDestino = '/var/www/html/uploads/'; // Ajustar la ruta si es necesario para llegar a la raíz/uploads # 116
        
        // Crear el directorio si no existe
        if (!is_dir($directorioDestino)) {
            mkdir($directorioDestino, 0755, true);
        }

        $rutaFisica = $directorioDestino . $nombreUnico;

        // 4. Mover el archivo físico
        if (!move_uploaded_file($file['tmp_name'], $rutaFisica)) {
            throw new \Exception("No se pudo guardar el archivo en el servidor. Verifique los permisos de la carpeta uploads.");
        }

        // 5. Preparar datos para la base de datos
        // La ruta que guardamos en BD debe ser relativa para la web (ej: /uploads/mi_archivo.pdf)
        $rutaWeb = '/uploads/' . $nombreUnico;
        
        $datosBD = [
            'id_categoria'     => $meta['id_categoria'] ?: 1, // Categoría por defecto si no viene
            'titulo'           => $meta['titulo'] ?: $file['name'],
            'ruta_archivo'     => $rutaWeb,
            'documento_activo' => 1,
            'ci_funcionario'   => $meta['ci_funcionario'] ?: 1 // Idealmente un CI válido de tu BD
        ];

        // 6. Guardar en base de datos
        if (!$this->crear($datosBD)) {
            // Si falla la BD, borramos el archivo físico para no dejar basura
            unlink($rutaFisica);
            throw new \Exception("Error al guardar el registro en la base de datos.");
        }

        return (int) $this->conexion->lastInsertId();

    /**
     * Obtiene todos los documentos activos que pertenecen a una categoría específica, 
     * buscándola por su slug (Issue # 110).
     */
    public function obtenerPorSlugCategoria(string $slug): array {
        $sql = "SELECT d.*, c.nombre_categoria, c.slug 
                FROM documentos d 
                INNER JOIN categorias_documentos c ON d.id_categoria = c.id 
                WHERE c.slug = :slug AND d.documento_activo = 1
                ORDER BY d.created_at DESC";
                
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute(['slug' => $slug]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}