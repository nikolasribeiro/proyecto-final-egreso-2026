<?php

declare(strict_types=1);

namespace Modelos;

use Nucleo\Conexion;
use Nucleo\Sesion;
use PDO;
use Throwable;

/**
 * ModeloPermiso — CRUD contra `permisos_rol` (issue #130).
 *
 * Cada celda de la matriz de permisos vive en una fila con PK compuesta
 * (id_rol, recurso, accion). `Roles::permiso()` consume estos datos con
 * cache lazy estático por proceso; `alternar()` es el camino de escritura
 * desde la UI.
 *
 * Deuda técnica: helper `registrarAuditoria` copiado literal de
 * `ModeloTraslado` y `ModeloUsuario`. Refactor pendiente a un trait o
 * helper en `Nucleo\`. NO se aborda acá.
 */
class ModeloPermiso
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexion::obtenerInstancia();
    }

    /**
     * Devuelve la matriz efectiva de permisos agrupada por rol:
     *   [id_rol][recurso][accion] => bool
     *
     * Si la tabla `permisos_rol` está vacía (BD recién creada o sin
     * seed), devuelve []. `Roles::permiso()` usa esto y, si la celda no
     * está, cae al fallback hardcodeado de `Roles::matriz()`.
     *
     * @return array<int, array<string, array<string, bool>>>
     */
    public function obtenerMatriz(): array
    {
        $stmt = $this->db->query(
            'SELECT id_rol, recurso, accion, permitido FROM permisos_rol'
        );
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($filas as $f) {
            $idRol  = (int)$f['id_rol'];
            $rec    = (string)$f['recurso'];
            $acc    = (string)$f['accion'];
            $permit = (bool)$f['permitido'];
            $out[$idRol][$rec][$acc] = $permit;
        }
        return $out;
    }

    /**
     * Catálogo id_rol → tipo_rol. Útil para que la UI muestre el nombre
     * legible del rol y para que `Roles::permiso()` resuelva
     * (id, recurso, acción) sin tener que cruzar tipo_rol cada vez.
     *
     * @return array<int, string>
     */
    public function obtenerRoles(): array
    {
        $stmt = $this->db->query('SELECT id, tipo_rol FROM roles ORDER BY id');
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $out[(int)$f['id']] = (string)$f['tipo_rol'];
        }
        return $out;
    }

    /**
     * Setea el valor de una celda. Idempotente: si el row no existe,
     * lo crea; si existe, lo actualiza.
     *
     * Devuelve un array con `antes` y `despues` (bool) para auditoría.
     * Si el (id_rol, recurso, accion) no existe ni en la matriz
     * hardcodeada ni en la BD, lanza InvalidArgumentException (no se
     * permite escribir permisos arbitrarios).
     *
     * @return array{antes: bool, despues: bool}
     */
    public function alternar(
        int $idRol,
        string $recurso,
        string $accion,
        bool $permitido,
        int $usuarioId
    ): array {
        // Validar contra el catálogo: si (rol, recurso, accion) no es
        // una combinación válida de Roles::matriz(), la celda no debería
        // existir en BD.
        $rolesUi = \Nucleo\Constantes\Roles::mapEnumToUi($this->tipoRolDeId($idRol));
        $matrizHardcoded = \Nucleo\Constantes\Roles::matriz();
        if ($rolesUi === null || !isset($matrizHardcoded[$rolesUi][$recurso][$accion])) {
            throw new \InvalidArgumentException(
                "Combinación inválida: rol={$idRol} ({$rolesUi}), recurso='{$recurso}', acción='{$accion}'."
            );
        }

        // Leer valor actual para el diff de auditoría.
        $stmt = $this->db->prepare(
            'SELECT permitido FROM permisos_rol
             WHERE id_rol = :r AND recurso = :rec AND accion = :acc LIMIT 1'
        );
        $stmt->execute([
            'r'   => $idRol,
            'rec' => $recurso,
            'acc' => $accion,
        ]);
        $antes = $stmt->fetchColumn();
        $antesBool = $antes === false ? null : (bool)$antes;

        // INSERT … ON DUPLICATE KEY UPDATE para mantener una sola fila
        // por (id_rol, recurso, accion). Idempotente.
        $this->db->prepare(
            'INSERT INTO permisos_rol
                 (id_rol, recurso, accion, permitido, updated_at, updated_by)
             VALUES (:r, :rec, :acc, :p, NOW(), :u)
             ON DUPLICATE KEY UPDATE
                 permitido = VALUES(permitido),
                 updated_at = NOW(),
                 updated_by = VALUES(updated_by)'
        )->execute([
            'r'   => $idRol,
            'rec' => $recurso,
            'acc' => $accion,
            'p'   => $permitido ? 1 : 0,
            'u'   => $usuarioId,
        ]);

        $this->registrarAuditoria('PERMISO_TOGGLE', 'permisos_rol', $idRol, [
            'recurso'        => $recurso,
            'accion'         => $accion,
            'antes'          => $antesBool,
            'despues'        => $permitido,
            'actualizado_por' => $usuarioId,
        ]);

        // Forzar recarga del cache lazy para que el próximo acceso a
        // Roles::permiso() vea el valor nuevo (issue #130).
        \Nucleo\Constantes\Roles::invalidarCachePermisos();

        return [
            'antes'   => (bool)($antesBool ?? $matrizHardcoded[$rolesUi][$recurso][$accion]),
            'despues' => $permitido,
        ];
    }

    /**
     * Aplica varios toggles en una sola transacción. Si alguno falla,
     * se hace rollback de todo. Devuelve un array con los diffs.
     *
     * @param array<int, array{id_rol:int, recurso:string, accion:string, permitido:bool}> $toggles
     * @return array<int, array{antes:bool, despues:bool}>
     */
    public function alternarBatch(array $toggles, int $usuarioId): array
    {
        if (empty($toggles)) {
            return [];
        }

        $resultados = [];
        $this->db->beginTransaction();
        try {
            foreach ($toggles as $t) {
                $resultados[] = $this->alternar(
                    (int)$t['id_rol'],
                    (string)$t['recurso'],
                    (string)$t['accion'],
                    (bool)$t['permitido'],
                    $usuarioId
                );
            }
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
        return $resultados;
    }

    /**
     * Devuelve el `tipo_rol` (enum SQL) del id de rol pasado. Helper
     * chico para no repetir la query en cada validación.
     */
    private function tipoRolDeId(int $idRol): string
    {
        $stmt = $this->db->prepare('SELECT tipo_rol FROM roles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $idRol]);
        return (string)($stmt->fetchColumn() ?: '');
    }

    /**
     * Helper de auditoría — copy-paste literal de `ModeloTraslado` y
     * `ModeloUsuario`. Deuda técnica pendiente de refactor.
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