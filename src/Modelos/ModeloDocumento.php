<?php

namespace Modelos;

use Nucleo\Conexion;
use Nucleo\Sesion;
use PDO;
use Exception;
use Throwable;

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
            WHERE d.documento_activo = 1
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

    /**
     * Actualiza un documento. Permite cambiar el título, la categoría y,
     * opcionalmente, reemplazar el archivo en disco.
     *
     * @param int   $id
     * @param array $datos  ['titulo', 'id_categoria', 'archivo' (opcional FileUpload)]
     * @return array{ruta_archivo:string,ruta_anterior:?string,detalles:array}
     *         Devuelve la ruta nueva, la anterior (si reemplazó archivo) y
     *         los detalles a registrar en auditoría.
     * @throws Exception
     */
    public function actualizarDocumento(int $id, array $datos): array
    {
        $actual = $this->obtenerPorId($id);
        if (!$actual) {
            throw new Exception("El documento no existe.");
        }

        $titulo           = trim((string)($datos['titulo'] ?? ''));
        $idCategoriaInput = $datos['id_categoria'] ?? null;
        $archivo          = $datos['archivo'] ?? null;
        $nuevaRutaWeb     = $actual['ruta_archivo'];
        $rutaAnterior     = null;

        if ($titulo === '') {
            throw new Exception("El título no puede estar vacío.");
        }

        $nuevoIdCategoria = $this->obtenerIdCategoria($idCategoriaInput);

        // Reemplazo de archivo: solo si llega un archivo válido en la request.
        $seReemplazoArchivo = is_array($archivo)
            && isset($archivo['error'])
            && $archivo['error'] === UPLOAD_ERR_OK;

        if ($seReemplazoArchivo) {
            if (($archivo['type'] ?? '') !== 'application/pdf') {
                throw new Exception("El archivo debe ser un PDF.");
            }

            $nombreOriginal   = pathinfo($archivo['name'], PATHINFO_FILENAME);
            $nombreSanitizado = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nombreOriginal);
            $nombreUnico      = uniqid($nombreSanitizado . '_') . '.pdf';

            $directorioDestino = __DIR__ . '/../uploads/';
            if (!is_dir($directorioDestino)) {
                mkdir($directorioDestino, 0755, true);
            }
            $rutaFisicaNueva = $directorioDestino . $nombreUnico;
            if (!move_uploaded_file($archivo['tmp_name'], $rutaFisicaNueva)) {
                throw new Exception("No se pudo guardar el nuevo PDF en el servidor.");
            }

            $nuevaRutaWeb = '/uploads/' . $nombreUnico;
            $rutaAnterior = $actual['ruta_archivo'];
        }

        try {
            $this->conexion->beginTransaction();

            $sql = "UPDATE documentos
                       SET titulo = :titulo,
                           id_categoria = :id_categoria,
                           ruta_archivo = :ruta_archivo,
                           updated_at = NOW()
                     WHERE id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([
                'titulo'       => $titulo,
                'id_categoria' => $nuevoIdCategoria,
                'ruta_archivo' => $nuevaRutaWeb,
                'id'           => $id,
            ]);

            // Solo borramos el archivo viejo si la actualización en BD
            // afectó filas. Si no, no tocamos el filesystem.
            if ($stmt->rowCount() === 0 && !$seReemplazoArchivo) {
                $this->conexion->rollBack();
                throw new Exception("No se realizaron cambios en el documento.");
            }

            $this->conexion->commit();
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            // Si la transacción falla pero ya escribimos un archivo nuevo,
            // lo borramos para no dejar basura.
            if ($seReemplazoArchivo && isset($rutaFisicaNueva) && is_file($rutaFisicaNueva)) {
                @unlink($rutaFisicaNueva);
            }
            throw $e;
        }

        // Recién acá, con la BD commiteada, borramos el archivo viejo.
        if ($seReemplazoArchivo && $rutaAnterior) {
            $rutaFisicaVieja = __DIR__ . '/..' . $rutaAnterior;
            if (is_file($rutaFisicaVieja)) {
                @unlink($rutaFisicaVieja);
            }
        }

        $detalles = [
            'titulo_anterior'      => $actual['titulo'],
            'titulo_nuevo'         => $titulo,
            'id_categoria_anterior' => (int)$actual['id_categoria'],
            'id_categoria_nuevo'    => $nuevoIdCategoria,
            'archivo_reemplazado'   => $seReemplazoArchivo,
        ];
        if ($seReemplazoArchivo) {
            $detalles['ruta_anterior'] = $rutaAnterior;
            $detalles['ruta_nueva']    = $nuevaRutaWeb;
        }

        $this->registrarAuditoria('ACTUALIZAR', 'documentos', $id, $detalles);

        return [
            'ruta_archivo'  => $nuevaRutaWeb,
            'ruta_anterior' => $rutaAnterior,
            'detalles'      => $detalles,
        ];
    }

    /**
     * Soft delete: marca documento_activo = FALSE. El archivo físico
     * se conserva (auditoría /復元). El documento deja de aparecer en
     * las consultas de `obtenerTodos` y `obtenerPorSlugCategoria`.
     */
    public function eliminarDocumento(int $id): bool
    {
        $actual = $this->obtenerPorId($id);
        if (!$actual) {
            throw new Exception("El documento no existe.");
        }
        if ((int)$actual['documento_activo'] === 0) {
            // Ya estaba inactivo. Lo tratamos como éxito idempotente.
            return true;
        }

        $sql = "UPDATE documentos
                   SET documento_activo = 0,
                       updated_at = NOW()
                 WHERE id = :id AND documento_activo = 1";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute(['id' => $id]);

        $ok = $stmt->rowCount() > 0;

        if ($ok) {
            $this->registrarAuditoria('ELIMINAR', 'documentos', $id, [
                'titulo'      => $actual['titulo'],
                'id_categoria' => (int)$actual['id_categoria'],
                'ruta_archivo' => $actual['ruta_archivo'],
                'soft_delete'  => true,
            ]);
        }

        return $ok;
    }

    /**
     * Helper copiado de ModeloTraslado (línea 889). La auditoría nunca
     * debe romper el flujo principal: si falla, solo se loguea.
     */
    private function registrarAuditoria(string $accion, string $tabla, int $registroId, array $detalles): void
    {
        try {
            $sql = "INSERT INTO logs_auditoria
                    (id_usuario, accion, tabla_afectada, registro_id, detalles, ip_origen, fecha_hora)
                    VALUES (:u, :a, :t, :r, :d, :ip, NOW())";
            $stmt = $this->conexion->prepare($sql);
            $user = Sesion::obtener('user');
            $stmt->execute([
                'u'  => $user['id'] ?? null,
                'a'  => $accion,
                't'  => $tabla,
                'r'  => $registroId,
                'd'  => json_encode($detalles, JSON_UNESCAPED_UNICODE),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable $e) {
            error_log('Auditoria fallo: ' . $e->getMessage());
        }
    }
}
