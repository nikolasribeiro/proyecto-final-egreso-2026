<?php

namespace Modelos;

use Nucleo\Conexion;
use PDO;

class ModeloDocumento
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexion::obtenerInstancia();
    }

    /**
     * Obtiene las categorías disponibles para los formularios de documentos.
     */
    public function obtenerCategorias(): array
    {
        $stmt = $this->db->query(
            "SELECT id, nombre_categoria
             FROM categorias_documentos
             ORDER BY nombre_categoria ASC"
        );

        return array_map(function (array $categoria): array {
            $categoria['id'] = (int) $categoria['id'];
            $categoria['slug'] = $this->generarSlug($categoria['nombre_categoria']);
            return $categoria;
        }, $stmt->fetchAll());
    }

    public function obtenerCategoriaPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, nombre_categoria
             FROM categorias_documentos
             WHERE id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $categoria = $stmt->fetch();

        if (!$categoria) {
            return null;
        }

        $categoria['id'] = (int) $categoria['id'];
        $categoria['slug'] = $this->generarSlug($categoria['nombre_categoria']);
        return $categoria;
    }

    /**
     * Crea una categoría, devolviendo null si ya existe otra con el mismo nombre.
     */
    public function crearCategoria(string $nombre): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, nombre_categoria
             FROM categorias_documentos
             WHERE LOWER(nombre_categoria) = LOWER(:nombre)
             LIMIT 1"
        );
        $stmt->execute(['nombre' => $nombre]);

        if ($stmt->fetch()) {
            return null;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO categorias_documentos (nombre_categoria)
             VALUES (:nombre)"
        );
        $stmt->execute(['nombre' => $nombre]);

        $categoria = [
            'id' => (int) $this->db->lastInsertId(),
            'nombre_categoria' => $nombre,
        ];
        $categoria['slug'] = $this->generarSlug($nombre);

        return $categoria;
    }

    /**
     * Obtiene documentos activos con el formato consumido por las vistas.
     */
    public function obtenerTodos(): array
    {
        $sql = "SELECT d.*, c.nombre_categoria,
                       CONCAT(u.nombre, ' ', u.apellido) AS funcionario
                FROM documentos d
                JOIN categorias_documentos c ON d.id_categoria = c.id
                JOIN usuarios u ON d.ci_funcionario = u.ci
                WHERE d.documento_activo = TRUE
                ORDER BY d.created_at DESC";

        $documentos = $this->db->query($sql)->fetchAll();
        return array_map(fn(array $documento): array => $this->normalizarDocumento($documento), $documentos);
    }

    public function obtenerPorCategoria(string $slug): array
    {
        return array_values(array_filter(
            $this->obtenerTodos(),
            fn(array $documento): bool => $documento['categoria']['slug'] === $slug
        ));
    }

    public function obtenerNombreCategoriaPorSlug(string $slug): ?string
    {
        foreach ($this->obtenerCategorias() as $categoria) {
            if ($categoria['slug'] === $slug) {
                return $categoria['nombre_categoria'];
            }
        }

        return null;
    }

    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT d.*, c.nombre_categoria
             FROM documentos d
             JOIN categorias_documentos c ON d.id_categoria = c.id
             WHERE d.id = :id"
        );
        $stmt->execute(['id' => $id]);
        $documento = $stmt->fetch();

        return $documento ? $this->normalizarDocumento($documento) : null;
    }

    public function crear(array $datos): bool
    {
        $sql = "INSERT INTO documentos
                    (id_categoria, titulo, ruta_archivo, documento_activo, ci_funcionario)
                VALUES (:id_categoria, :titulo, :ruta_archivo, :documento_activo, :ci_funcionario)";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'id_categoria'     => $datos['id_categoria'],
            'titulo'           => $datos['titulo'],
            'ruta_archivo'     => $datos['ruta_archivo'],
            'documento_activo' => $datos['documento_activo'] ?? true,
            'ci_funcionario'   => $datos['ci_funcionario'],
        ]);
    }

    private function normalizarDocumento(array $documento): array
    {
        $ruta = (string) ($documento['ruta_archivo'] ?? '');
        $rutaFisica = dirname(__DIR__) . '/' . ltrim($ruta, '/');
        $tamanoBytes = is_file($rutaFisica) ? (int) filesize($rutaFisica) : 0;
        $extension = pathinfo($ruta, PATHINFO_EXTENSION);
        $nombreCategoria = (string) ($documento['nombre_categoria'] ?? 'General');

        return [
            'id' => (int) $documento['id'],
            'nombre' => (string) $documento['titulo'],
            'tipo' => $extension !== '' ? strtoupper($extension) : 'DOCUMENTO',
            'tamano' => $this->formatearTamano($tamanoBytes),
            'fecha_subida' => $this->formatearFecha($documento['created_at'] ?? null),
            'ruta' => $ruta,
            'categoria' => [
                'slug' => $this->generarSlug($nombreCategoria),
                'nombre' => $nombreCategoria,
            ],
        ];
    }

    private function formatearTamano(int $bytes): string
    {
        if ($bytes <= 0) {
            return 'Tamaño no disponible';
        }

        $unidades = ['B', 'KB', 'MB', 'GB'];
        $indice = 0;
        $tamano = (float) $bytes;

        while ($tamano >= 1024 && $indice < count($unidades) - 1) {
            $tamano /= 1024;
            $indice++;
        }

        return number_format($tamano, $indice === 0 ? 0 : 1, ',', '.') . ' ' . $unidades[$indice];
    }

    private function formatearFecha(?string $fecha): string
    {
        if (!$fecha) {
            return 'Fecha no disponible';
        }

        $timestamp = strtotime($fecha);
        return $timestamp ? date('d/m/Y', $timestamp) : 'Fecha no disponible';
    }

    private function generarSlug(string $texto): string
    {
        $textoAscii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        $textoAscii = $textoAscii !== false ? $textoAscii : $texto;
        $slug = strtolower($textoAscii);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-') ?: 'general';
    }
}
