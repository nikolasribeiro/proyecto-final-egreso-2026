<?php

declare(strict_types=1);

namespace Modelos;

use Nucleo\Conexion;
use Nucleo\Sesion;
use PDO;
use Throwable;

/**
 * ModeloVehiculo — CRUD contra `vehiculos` + catálogo `tipo_vehiculo`.
 *
 * Issue #131: gestión admin de vehículos + liberación automática al
 * completar/cancelar un traslado (los hooks `ocuparVehiculo` /
 * `liberarVehiculo` viven en `ModeloTraslado`, no acá).
 *
 * Soft delete vía columna `activo BOOLEAN DEFAULT TRUE` (separada de
 * `estado` ENUM que representa disponibilidad operativa). La unicidad de
 * `matricula` la garantiza la BD (UNIQUE constraint); el modelo además
 * hace un pre-check para devolver mensajes amigables.
 *
 * Deuda técnica: `registrarAuditoria` está duplicado literal respecto de
 * `ModeloTraslado` y `ModeloUsuario`. Refactor pendiente a un trait en
 * `Nucleo\` — no se aborda acá.
 */
class ModeloVehiculo
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexion::obtenerInstancia();
    }

    /**
     * Lista vehículos aplicando filtros server-side.
     *
     * Filtros (todos opcionales):
     *   - estado:    'disponibles' | 'no_disponibles' | 'todos' (default 'todos')
     *   - tipo:      id de `tipo_vehiculo` (int) o 0 = todos
     *   - activo:    'activos' | 'inactivos' | 'todos' (default 'todos')
     *   - q:         texto libre contra matricula (LIKE)
     *   - pagina:    número de página (1-indexed). default 1.
     *   - por_pagina: tamaño de página (default 25, máximo 100).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listar(
        string $estado = 'todos',
        int $tipo = 0,
        string $activo = 'todos',
        string $q = '',
        int $pagina = 1,
        int $porPagina = 25
    ): array {
        $pagina = max(1, $pagina);
        $porPagina = max(1, min(100, $porPagina));
        $offset = ($pagina - 1) * $porPagina;

        [$whereSql, $params] = $this->armarFiltros($estado, $tipo, $activo, $q);

        $sql = "SELECT v.id, v.estado, v.matricula, v.id_tipo_vehiculo, v.activo,
                       tv.descripcion AS tipo_vehiculo
                FROM vehiculos v
                JOIN tipo_vehiculo tv ON tv.id = v.id_tipo_vehiculo
                {$whereSql}
                ORDER BY tv.descripcion ASC, v.matricula ASC
                LIMIT :lim OFFSET :off";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':lim', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'hidratarFila'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Cuenta vehículos aplicando los mismos filtros que `listar()`.
     */
    public function contar(
        string $estado = 'todos',
        int $tipo = 0,
        string $activo = 'todos',
        string $q = ''
    ): int {
        [$whereSql, $params] = $this->armarFiltros($estado, $tipo, $activo, $q);

        $sql = "SELECT COUNT(*) AS total
                FROM vehiculos v
                JOIN tipo_vehiculo tv ON tv.id = v.id_tipo_vehiculo
                {$whereSql}";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->execute();
        return (int)$stmt->fetch()['total'];
    }

    /**
     * Resumen del universo completo por estado operativo.
     *
     * No aplica filtros: cuenta sobre todos los vehículos (activos e
     * inactivos) para que las tarjetas sean siempre un resumen global.
     *
     * @return array{total: int, disponibles: int, no_disponibles: int}
     */
    public function contarPorEstado(): array
    {
        $sql = "SELECT
                    COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN estado = 'DISPONIBLE' THEN 1 ELSE 0 END), 0) AS disponibles,
                    COALESCE(SUM(CASE WHEN estado = 'NO-DISPONIBLE' THEN 1 ELSE 0 END), 0) AS no_disponibles
                FROM vehiculos";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total'          => (int)($fila['total'] ?? 0),
            'disponibles'    => (int)($fila['disponibles'] ?? 0),
            'no_disponibles' => (int)($fila['no_disponibles'] ?? 0),
        ];
    }

    /**
     * Catálogo de tipos de vehículo. Se cachea por request en una
     * propiedad estática lazy para evitar martillar la BD en cada
     * render del select.
     *
     * Si la tabla está vacía (BD pre-existente sin seed), siembra los
     * 4 tipos básicos de forma idempotente con `INSERT IGNORE`. Esto
     * garantiza que el CRUD funcione aunque el `init.sql` original no
     * se haya ejecutado en la BD actual (issue #131).
     *
     * @return array<int, array{id: int, descripcion: string}>
     */
    public function obtenerTiposVehiculo(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $stmt = $this->db->query('SELECT id, descripcion FROM tipo_vehiculo ORDER BY descripcion');
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Auto-seed defensivo: si la tabla está vacía, INSERT IGNORE
        // los 4 tipos básicos y volver a consultar.
        if (empty($filas)) {
                $this->db->exec(
                    "INSERT IGNORE INTO tipo_vehiculo (descripcion) VALUES
                     ('Ambulancia'),
                     ('Auto'),
                     ('Camión'),
                     ('Otro')"
                );
                $stmt = $this->db->query('SELECT id, descripcion FROM tipo_vehiculo ORDER BY descripcion');
                $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $cache = array_map(
            static fn(array $r) => ['id' => (int)$r['id'], 'descripcion' => (string)$r['descripcion']],
            $filas
        );
        return $cache;
    }

    /**
     * Devuelve un vehículo por id. Devuelve null si no existe.
     */
    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT v.id, v.estado, v.matricula, v.id_tipo_vehiculo, v.activo,
                    tv.descripcion AS tipo_vehiculo
             FROM vehiculos v
             JOIN tipo_vehiculo tv ON tv.id = v.id_tipo_vehiculo
             WHERE v.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? $this->hidratarFila($fila) : null;
    }

    /**
     * Crea un vehículo nuevo.
     *
     * @return int id del vehículo creado
     * @throws \InvalidArgumentException datos inválidos o matricula duplicada
     */
    public function crear(string $matricula, int $idTipoVehiculo): int
    {
        $matricula = strtoupper(trim($matricula));
        $idTipoVehiculo = (int)$idTipoVehiculo;

        if ($matricula === '') {
            throw new \InvalidArgumentException('La matrícula es obligatoria.');
        }
        if (mb_strlen($matricula) > 20) {
            throw new \InvalidArgumentException('La matrícula supera el largo máximo (20).');
        }
        if ($idTipoVehiculo <= 0) {
            throw new \InvalidArgumentException('Tipo de vehículo inválido.');
        }
        if (!$this->existeTipoVehiculo($idTipoVehiculo)) {
            throw new \InvalidArgumentException('Tipo de vehículo inexistente.');
        }
        if ($this->existeMatricula($matricula)) {
            throw new \InvalidArgumentException("Ya existe un vehículo con la matrícula {$matricula}.");
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO vehiculos (estado, matricula, id_tipo_vehiculo, activo)
                 VALUES (\'DISPONIBLE\', :matricula, :tipo, TRUE)'
            );
            $stmt->execute([
                'matricula' => $matricula,
                'tipo'      => $idTipoVehiculo,
            ]);
            $nuevoId = (int)$this->db->lastInsertId();
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        $this->registrarAuditoria('CREAR', 'vehiculos', $nuevoId, [
            'matricula'         => $matricula,
            'id_tipo_vehiculo'  => $idTipoVehiculo,
        ]);

        return $nuevoId;
    }

    /**
     * Actualiza matricula y tipo de un vehículo existente.
     * El estado operativo NO se modifica acá (lo gestiona `liberarVehiculo`
     * en `ModeloTraslado` o el admin manualmente vía baja/reactivar).
     *
     * @throws \InvalidArgumentException vehículo inexistente, matricula duplicada, etc.
     */
    public function actualizar(int $id, string $matricula, int $idTipoVehiculo): void
    {
        $vehiculo = $this->buscarPorId($id);
        if (!$vehiculo) {
            throw new \InvalidArgumentException("Vehículo con id {$id} no encontrado.");
        }

        $matricula = strtoupper(trim($matricula));
        $idTipoVehiculo = (int)$idTipoVehiculo;

        if ($matricula === '') {
            throw new \InvalidArgumentException('La matrícula es obligatoria.');
        }
        if (mb_strlen($matricula) > 20) {
            throw new \InvalidArgumentException('La matrícula supera el largo máximo (20).');
        }
        if ($idTipoVehiculo <= 0 || !$this->existeTipoVehiculo($idTipoVehiculo)) {
            throw new \InvalidArgumentException('Tipo de vehículo inválido.');
        }

        // Conflicto con OTRO vehículo (no consigo mismo)
        $otro = $this->buscarPorMatricula($matricula);
        if ($otro !== null && (int)$otro['id'] !== $id) {
            throw new \InvalidArgumentException("Ya existe otro vehículo con la matrícula {$matricula}.");
        }

        $stmt = $this->db->prepare(
            'UPDATE vehiculos
             SET matricula = :matricula, id_tipo_vehiculo = :tipo
             WHERE id = :id'
        );
        $stmt->execute([
            'matricula' => $matricula,
            'tipo'      => $idTipoVehiculo,
            'id'        => $id,
        ]);

        $cambios = [];
        if ($vehiculo['matricula'] !== $matricula) {
            $cambios['matricula'] = ['antes' => $vehiculo['matricula'], 'despues' => $matricula];
        }
        if ((int)$vehiculo['id_tipo_vehiculo'] !== $idTipoVehiculo) {
            $cambios['id_tipo_vehiculo'] = ['antes' => $vehiculo['id_tipo_vehiculo'], 'despues' => $idTipoVehiculo];
        }

        if (!empty($cambios)) {
            $this->registrarAuditoria('ACTUALIZAR', 'vehiculos', $id, $cambios);
        }
    }

    /**
     * Soft delete: `activo = FALSE` y `estado = 'NO-DISPONIBLE'`.
     * Idempotente: si ya estaba inactivo, no es error.
     */
    public function desactivar(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE vehiculos
             SET activo = FALSE, estado = \'NO-DISPONIBLE\'
             WHERE id = :id AND activo = TRUE'
        );
        $stmt->execute(['id' => $id]);
        if ($stmt->rowCount() === 0) {
            $existe = $this->buscarPorId($id);
            if (!$existe) {
                throw new \InvalidArgumentException("Vehículo con id {$id} no encontrado.");
            }
            return;
        }
        $this->registrarAuditoria('ELIMINAR', 'vehiculos', $id, [
            'motivo' => 'soft_delete_admin',
            'estado_antes' => 'cualquiera',
            'estado_despues' => 'NO-DISPONIBLE',
        ]);
    }

    /**
     * Reactiva un vehículo: vuelve `activo = TRUE` Y `estado = 'DISPONIBLE'`.
     *
     * La baja es simétrica: `desactivar()` marca ambos flags como
     * inactivos, así que `reactivar()` también toca ambos para devolver
     * el vehículo al servicio de forma consistente. Esto evita el bug
     * donde el vehículo quedaba `activo = TRUE` pero `estado =
     * 'NO-DISPONIBLE'`, haciéndolo invisible para el wizard de traslados.
     *
     * Idempotente.
     */
    public function reactivar(int $id): void
    {
        $stmt = $this->db->prepare(
            "UPDATE vehiculos
             SET activo = TRUE, estado = 'DISPONIBLE'
             WHERE id = :id AND activo = FALSE"
        );
        $stmt->execute(['id' => $id]);
        if ($stmt->rowCount() === 0) {
            $existe = $this->buscarPorId($id);
            if (!$existe) {
                throw new \InvalidArgumentException("Vehículo con id {$id} no encontrado.");
            }
            return;
        }
        $this->registrarAuditoria('ACTUALIZAR', 'vehiculos', $id, [
            'motivo' => 'reactivar_admin',
            'estado_anterior' => 'NO-DISPONIBLE',
            'estado_nuevo'    => 'DISPONIBLE',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Internals
    // ─────────────────────────────────────────────────────────────────

    /**
     * Construye el WHERE + parámetros para `listar`/`contar`.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function armarFiltros(
        string $estado,
        int $tipo,
        string $activo,
        string $q
    ): array {
        $condiciones = [];
        $params = [];

        if ($estado === 'disponibles') {
            $condiciones[] = 'v.estado = \'DISPONIBLE\'';
        } elseif ($estado === 'no_disponibles') {
            $condiciones[] = 'v.estado = \'NO-DISPONIBLE\'';
        }

        if ($activo === 'activos') {
            $condiciones[] = 'v.activo = TRUE';
        } elseif ($activo === 'inactivos') {
            $condiciones[] = 'v.activo = FALSE';
        }

        if ($tipo > 0) {
            $condiciones[] = 'v.id_tipo_vehiculo = :tipo';
            $params['tipo'] = $tipo;
        }

        $q = trim($q);
        if ($q !== '') {
            $condiciones[] = 'v.matricula LIKE :q_mat';
            $params['q_mat'] = '%' . $q . '%';
        }

        $whereSql = empty($condiciones) ? '' : 'WHERE ' . implode(' AND ', $condiciones);
        return [$whereSql, $params];
    }

    /**
     * Normaliza la fila para la UI: bool a nativo PHP + campos derivados.
     */
    private function hidratarFila(array $fila): array
    {
        $fila['id'] = (int)$fila['id'];
        $fila['id_tipo_vehiculo'] = (int)$fila['id_tipo_vehiculo'];
        $fila['activo'] = (bool)$fila['activo'];
        $fila['estado_legible'] = $fila['estado'] === 'DISPONIBLE' ? 'Disponible' : 'No disponible';
        $fila['activo_legible'] = $fila['activo'] ? 'Activo' : 'Inactivo';
        return $fila;
    }

    private function existeMatricula(string $matricula): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM vehiculos WHERE matricula = :m LIMIT 1'
        );
        $stmt->execute(['m' => $matricula]);
        return (bool)$stmt->fetchColumn();
    }

    private function buscarPorMatricula(string $matricula): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM vehiculos WHERE matricula = :m LIMIT 1'
        );
        $stmt->execute(['m' => $matricula]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? ['id' => (int)$fila['id']] : null;
    }

    private function existeTipoVehiculo(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM tipo_vehiculo WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Helper de auditoría — copia literal de `ModeloTraslado:666`.
     *
     * Deuda técnica: este helper está duplicado en cada modelo que muta.
     * Refactor pendiente a un trait o helper en `Nucleo\`. NO se aborda acá.
     */
    private function registrarAuditoria(string $accion, string $tabla, int $registroId, array $detalles): void
    {
        try {
            $sql = "INSERT INTO logs_auditoria
                    (id_usuario, accion, tabla_afectada, registro_id, detalles, ip_origen, fecha_hora)
                    VALUES (:u, :a, :t, :r, :d, :ip, NOW())";
            $stmt = $this->db->prepare($sql);
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