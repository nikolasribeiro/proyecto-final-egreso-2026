<?php

declare(strict_types=1);

namespace Modelos;

use Nucleo\Conexion;
use Nucleo\Sesion;
use PDO;
use Throwable;

/**
 * ModeloUsuario — CRUD contra `usuarios` + pivot `usuario_roles`.
 *
 * Reemplaza al mock `Nucleo\Constantes\Usuarios` (issue #127). La fuente de
 * verdad ahora es la base de datos; las mutaciones CUD quedan registradas
 * en `logs_auditoria` mediante el helper `registrarAuditoria()` copiado
 * literalmente de `ModeloTraslado` (deuda técnica: refactor a trait/helper
 * en Nucleo — no se aborda acá).
 */
class ModeloUsuario
{
    private PDO $db;

    /**
     * Mapa UI key (`Roles::labels`) → `roles.tipo_rol` en MySQL.
     *
     * Es el único punto de traducción entre el "nombre de rol" que conoce
     * la UI / matriz de permisos (`administrador`, `medico`, `enfermero`,
     * `soporte_tecnico`) y el enum interno de la BD (`ADMINISTRATIVO`,
     * `MEDICO`, `ENFERMERO`, `SOPORTE_TECNICO`). El catálogo `Roles::labels()`
     * usa el UI key; el filtro operativo de traslados (`tipo_rol = 'CHOFER'`)
     * usa el enum BD.
     *
     * @var array<string, string>
     */
    public const ROL_UI_A_DB = [
        'administrador'   => 'ADMINISTRATIVO',
        'medico'          => 'MEDICO',
        'enfermero'       => 'ENFERMERO',
        'chofer'          => 'CHOFER',
        'soporte_tecnico' => 'SOPORTE_TECNICO',
    ];

    /**
     * Mapa inverso: enum BD → UI key (para hidratar badges).
     *
     * @var array<string, string>
     */
    public const ROL_DB_A_UI = [
        'ADMINISTRATIVO'   => 'administrador',
        'MEDICO'           => 'medico',
        'ENFERMERO'        => 'enfermero',
        'CHOFER'           => 'chofer',
        'SOPORTE_TECNICO'  => 'soporte_tecnico',
    ];

    public function __construct()
    {
        $this->db = Conexion::obtenerInstancia();
    }

    /**
     * Lista usuarios aplicando filtros server-side.
     *
     * Filtros (todos opcionales):
     *   - estado: 'activos' | 'inactivos' | 'todos' (default 'todos')
     *   - rol:    UI key (`administrador`, `medico`, etc.) o vacío (todos)
     *   - q:      texto libre contra ci / nombre / apellido / email (LIKE)
     *   - pagina: número de página (1-indexed). default 1.
     *   - por_pagina: tamaño de página (default 25, máximo 100).
     *
     * Devuelve la lista de usuarios con `roles` ya hidratada como array
     * de UI keys + `roles_etiquetas` con el label legible (Roles::labels).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listar(
        string $estado = 'todos',
        string $rol = '',
        string $q = '',
        int $pagina = 1,
        int $porPagina = 25
    ): array {
        $pagina = max(1, $pagina);
        $porPagina = max(1, min(100, $porPagina));
        $offset = ($pagina - 1) * $porPagina;

        $condiciones = [];
        $params = [];

        if ($estado === 'activos') {
            $condiciones[] = 'u.activo = TRUE';
        } elseif ($estado === 'inactivos') {
            $condiciones[] = 'u.activo = FALSE';
        }

        if ($rol !== '' && isset(self::ROL_UI_A_DB[$rol])) {
            $tipoRolBd = self::ROL_UI_A_DB[$rol];
            $condiciones[] = 'EXISTS (SELECT 1 FROM usuario_roles ur2 '
                . 'JOIN roles r2 ON ur2.id_rol = r2.id '
                . 'WHERE ur2.id_usuario = u.id AND r2.tipo_rol = :tipo_rol_filtro)';
            $params['tipo_rol_filtro'] = $tipoRolBd;
        }

        $q = trim($q);
        if ($q !== '') {
            $like = '%' . $q . '%';
            $condiciones[] = '(CAST(u.ci AS CHAR) LIKE :q_ci '
                . 'OR u.nombre LIKE :q_nombre '
                . 'OR u.apellido LIKE :q_apellido '
                . 'OR u.email LIKE :q_email)';
            $params['q_ci']       = $like;
            $params['q_nombre']   = $like;
            $params['q_apellido'] = $like;
            $params['q_email']    = $like;
        }

        $where = empty($condiciones) ? '' : 'WHERE ' . implode(' AND ', $condiciones);

        $sql = "SELECT u.id, u.ci, u.nombre, u.apellido, u.email,
                       u.activo, u.fecha_alta,
                       (SELECT GROUP_CONCAT(r.tipo_rol ORDER BY r.tipo_rol SEPARATOR ',')
                          FROM usuario_roles ur
                          JOIN roles r ON ur.id_rol = r.id
                          WHERE ur.id_usuario = u.id) AS roles_db
                FROM usuarios u
                {$where}
                ORDER BY u.apellido ASC, u.nombre ASC
                LIMIT :lim OFFSET :off";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':lim', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hidratarFila'], $filas);
    }

    /**
     * Cuenta usuarios aplicando los mismos filtros que `listar()`.
     * Útil para paginación.
     */
    public function contar(
        string $estado = 'todos',
        string $rol = '',
        string $q = ''
    ): int {
        $condiciones = [];
        $params = [];

        if ($estado === 'activos') {
            $condiciones[] = 'u.activo = TRUE';
        } elseif ($estado === 'inactivos') {
            $condiciones[] = 'u.activo = FALSE';
        }

        if ($rol !== '' && isset(self::ROL_UI_A_DB[$rol])) {
            $tipoRolBd = self::ROL_UI_A_DB[$rol];
            $condiciones[] = 'EXISTS (SELECT 1 FROM usuario_roles ur2 '
                . 'JOIN roles r2 ON ur2.id_rol = r2.id '
                . 'WHERE ur2.id_usuario = u.id AND r2.tipo_rol = :tipo_rol_filtro)';
            $params['tipo_rol_filtro'] = $tipoRolBd;
        }

        $q = trim($q);
        if ($q !== '') {
            $like = '%' . $q . '%';
            $condiciones[] = '(CAST(u.ci AS CHAR) LIKE :q_ci '
                . 'OR u.nombre LIKE :q_nombre '
                . 'OR u.apellido LIKE :q_apellido '
                . 'OR u.email LIKE :q_email)';
            $params['q_ci']       = $like;
            $params['q_nombre']   = $like;
            $params['q_apellido'] = $like;
            $params['q_email']    = $like;
        }

        $where = empty($condiciones) ? '' : 'WHERE ' . implode(' AND ', $condiciones);

        $sql = "SELECT COUNT(*) AS total FROM usuarios u {$where}";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->execute();
        return (int)$stmt->fetch()['total'];
    }

    /**
     * Cuenta el universo completo de usuarios por estado.
     *
     * Este resumen no recibe filtros y, por diseño, incluye tanto usuarios
     * activos como inactivos. Los valores se normalizan a int para que la
     * vista no dependa de los strings que devuelve PDO para los agregados.
     *
     * @return array{total: int, activos: int, inactivos: int}
     */
    public function contarPorEstado(): array
    {
        $sql = "SELECT
                    COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN activo = TRUE THEN 1 ELSE 0 END), 0) AS activos,
                    COALESCE(SUM(CASE WHEN activo = TRUE THEN 0 ELSE 1 END), 0) AS inactivos
                FROM usuarios";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total'     => (int)($fila['total'] ?? 0),
            'activos'   => (int)($fila['activos'] ?? 0),
            'inactivos' => (int)($fila['inactivos'] ?? 0),
        ];
    }

    /**
     * Cuenta el universo completo de usuarios por rol.
     *
     * Un usuario se cuenta una sola vez por cada rol, aunque existan filas
     * duplicadas en la pivot. El enum histórico CHOFER se presenta como
     * ENFERMERO, igual que en el resto del modelo y en la UI.
     *
     * Este resumen no aplica el filtro de estado: también incluye usuarios
     * inactivos para que las tarjetas sean siempre un resumen del universo.
     *
     * @return array{administrador: int, medico: int, enfermero: int, soporte_tecnico: int}
     */
    public function contarPorRol(): array
    {
        $sql = "SELECT
                    COUNT(DISTINCT CASE
                        WHEN r.tipo_rol = 'ADMINISTRATIVO' THEN u.id
                    END) AS administrador,
                    COUNT(DISTINCT CASE
                        WHEN r.tipo_rol = 'MEDICO' THEN u.id
                    END) AS medico,
                    COUNT(DISTINCT CASE
                        WHEN r.tipo_rol IN ('CHOFER', 'ENFERMERO') THEN u.id
                    END) AS enfermero,
                    COUNT(DISTINCT CASE
                        WHEN r.tipo_rol = 'SOPORTE_TECNICO' THEN u.id
                    END) AS soporte_tecnico
                FROM usuarios u
                INNER JOIN usuario_roles ur ON ur.id_usuario = u.id
                INNER JOIN roles r ON ur.id_rol = r.id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'administrador'   => (int)($fila['administrador'] ?? 0),
            'medico'          => (int)($fila['medico'] ?? 0),
            'enfermero'       => (int)($fila['enfermero'] ?? 0),
            'soporte_tecnico' => (int)($fila['soporte_tecnico'] ?? 0),
        ];
    }

    /**
     * Devuelve un usuario por CI (PK lógica de la app: las FKs de traslados
     * apuntan a `ci`, no a `id`). Devuelve null si no existe.
     */
    public function buscarPorCi(int $ci): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, ci, nombre, apellido, email, activo, fecha_alta
             FROM usuarios WHERE ci = :ci LIMIT 1'
        );
        $stmt->execute(['ci' => $ci]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }
        $fila['roles'] = $this->obtenerRolesUiPorUsuario((int)$fila['id']);
        return $fila;
    }

    /**
     * Devuelve un usuario por id de tabla. Devuelve null si no existe.
     */
    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, ci, nombre, apellido, email, activo, fecha_alta
             FROM usuarios WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }
        $fila['roles'] = $this->obtenerRolesUiPorUsuario((int)$fila['id']);
        return $fila;
    }

    /**
     * Catálogo de roles disponibles para asignar (UI keys del catálogo
     * `Roles::labels`). Útil para popular los checkboxes del formulario.
     *
     * @return array<int, array{clave: string, etiqueta: string, tipo_rol: string}>
     */
    public function obtenerCatalogoRoles(): array
    {
        $stmt = $this->db->query(
            'SELECT id, descripcion_rol, tipo_rol FROM roles ORDER BY tipo_rol'
        );
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Invertimos UI→BD para mapear el enum de la BD a la clave canónica
        // que usa la matriz de permisos.
        $bdAui = array_flip(self::ROL_UI_A_DB);
        $catalogo = [];
        foreach ($filas as $r) {
            $uiKey = $bdAui[$r['tipo_rol']] ?? null;
            if ($uiKey === null) {
                continue; // rol no presente en el catálogo de la UI
            }
            $catalogo[] = [
                'id' => (int)$r['id'],
                'clave' => $uiKey,
                'etiqueta' => $r['descripcion_rol'] ?? ucfirst($uiKey),
                'tipo_rol' => $r['tipo_rol'],
            ];
        }
        return $catalogo;
    }

    /**
     * Crea un usuario nuevo.
     *
     * @param array{
     *     ci: int,
     *     nombre: string,
     *     apellido: string,
     *     email: string,
     *     contrasena: string,
     *     roles: array<int, string>  // UI keys del catálogo
     * } $datos
     *
     * Devuelve el id del usuario creado. Lanza \InvalidArgumentException
     * si los datos no son válidos o si hay conflicto de unicidad (CI o email).
     */
    public function crear(array $datos): int
    {
        $ci         = (int)($datos['ci'] ?? 0);
        $nombre     = trim((string)($datos['nombre'] ?? ''));
        $apellido   = trim((string)($datos['apellido'] ?? ''));
        $email      = trim((string)($datos['email'] ?? ''));
        $contrasena = (string)($datos['contrasena'] ?? '');
        $rolesUi    = $datos['roles'] ?? [];

        if ($ci <= 0) {
            throw new \InvalidArgumentException('La CI debe ser un número positivo.');
        }
        if ($nombre === '' || $apellido === '') {
            throw new \InvalidArgumentException('Nombre y apellido son obligatorios.');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email inválido.');
        }
        if (strlen($contrasena) < 6) {
            throw new \InvalidArgumentException('La contraseña debe tener al menos 6 caracteres.');
        }
        if (mb_strlen($nombre) > 100 || mb_strlen($apellido) > 100 || mb_strlen($email) > 150) {
            throw new \InvalidArgumentException('Uno de los campos supera el largo máximo.');
        }
        if (!$this->rolesUiValidos($rolesUi)) {
            throw new \InvalidArgumentException('Alguno de los roles seleccionados no existe en el catálogo.');
        }

        // Pre-check de unicidad para mensajes de error claros (además de la UNIQUE constraint).
        if ($this->buscarPorCi($ci) !== null) {
            throw new \InvalidArgumentException("Ya existe un usuario con la CI {$ci}.");
        }
        if ($this->existeEmail($email)) {
            throw new \InvalidArgumentException("Ya existe un usuario con el email {$email}.");
        }

        $hash = password_hash($contrasena, PASSWORD_BCRYPT);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO usuarios (ci, nombre, apellido, email, contrasena, activo, fecha_alta)
                 VALUES (:ci, :nombre, :apellido, :email, :hash, TRUE, NOW())'
            );
            $stmt->execute([
                'ci'       => $ci,
                'nombre'   => $nombre,
                'apellido' => $apellido,
                'email'    => $email,
                'hash'     => $hash,
            ]);
            $nuevoId = (int)$this->db->lastInsertId();

            $this->asignarRoles($nuevoId, $rolesUi);

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        $this->registrarAuditoria('CREAR', 'usuarios', $nuevoId, [
            'ci' => $ci,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'email' => $email,
            'roles' => $rolesUi,
        ]);

        return $nuevoId;
    }

    /**
     * Actualiza nombre / apellido / email y reescribe los roles.
     * CI es inmutable post-alta (no se acepta en el payload).
     * Si `contrasena` viene no vacía, también la actualiza (hasheada).
     *
     * @param array{
     *     nombre?: string,
     *     apellido?: string,
     *     email?: string,
     *     contrasena?: string,
     *     roles?: array<int, string>
     * } $datos
     */
    public function actualizar(int $id, array $datos): void
    {
        $usuario = $this->buscarPorId($id);
        if (!$usuario) {
            throw new \InvalidArgumentException("Usuario con id {$id} no encontrado.");
        }

        $campos = [];
        $params = ['id' => $id];
        $cambios = [];

        if (array_key_exists('nombre', $datos)) {
            $nombre = trim((string)$datos['nombre']);
            if ($nombre === '') {
                throw new \InvalidArgumentException('El nombre no puede estar vacío.');
            }
            if (mb_strlen($nombre) > 100) {
                throw new \InvalidArgumentException('El nombre supera el largo máximo.');
            }
            $campos[] = 'nombre = :nombre';
            $params['nombre'] = $nombre;
            $cambios['nombre'] = ['antes' => $usuario['nombre'], 'despues' => $nombre];
        }

        if (array_key_exists('apellido', $datos)) {
            $apellido = trim((string)$datos['apellido']);
            if ($apellido === '') {
                throw new \InvalidArgumentException('El apellido no puede estar vacío.');
            }
            if (mb_strlen($apellido) > 100) {
                throw new \InvalidArgumentException('El apellido supera el largo máximo.');
            }
            $campos[] = 'apellido = :apellido';
            $params['apellido'] = $apellido;
            $cambios['apellido'] = ['antes' => $usuario['apellido'], 'despues' => $apellido];
        }

        if (array_key_exists('email', $datos)) {
            $email = trim((string)$datos['email']);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Email inválido.');
            }
            if (mb_strlen($email) > 150) {
                throw new \InvalidArgumentException('El email supera el largo máximo.');
            }
            if ($this->existeEmailExcepto($email, $id)) {
                throw new \InvalidArgumentException("Ya existe otro usuario con el email {$email}.");
            }
            $campos[] = 'email = :email';
            $params['email'] = $email;
            $cambios['email'] = ['antes' => $usuario['email'], 'despues' => $email];
        }

        if (!empty($datos['contrasena'])) {
            $contrasena = (string)$datos['contrasena'];
            if (strlen($contrasena) < 6) {
                throw new \InvalidArgumentException('La contraseña debe tener al menos 6 caracteres.');
            }
            $campos[] = 'contrasena = :hash';
            $params['hash'] = password_hash($contrasena, PASSWORD_BCRYPT);
            $cambios['contrasena'] = 'actualizada';
        }

        $rolesCambiados = false;
        $rolesAntes = $usuario['roles'];
        $rolesDespues = $rolesAntes;
        if (isset($datos['roles']) && is_array($datos['roles'])) {
            $rolesUi = $datos['roles'];
            if (!$this->rolesUiValidos($rolesUi)) {
                throw new \InvalidArgumentException('Alguno de los roles seleccionados no existe en el catálogo.');
            }
            $rolesDespues = array_values(array_unique(array_map('strval', $rolesUi)));
            sort($rolesDespues);
            $rolesAntesSorted = $rolesAntes;
            sort($rolesAntesSorted);
            $rolesCambiados = ($rolesDespues !== $rolesAntesSorted);
        }

        $this->db->beginTransaction();
        try {
            if (!empty($campos)) {
                $sql = 'UPDATE usuarios SET ' . implode(', ', $campos) . ' WHERE id = :id';
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }
            if ($rolesCambiados) {
                $this->asignarRoles($id, $rolesDespues);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        $detalles = $cambios;
        if ($rolesCambiados) {
            $detalles['roles'] = ['antes' => $rolesAntes, 'despues' => $rolesDespues];
        }
        if (!empty($detalles)) {
            $this->registrarAuditoria('ACTUALIZAR', 'usuarios', $id, $detalles);
        }
    }

    /**
     * Reemplaza la lista de roles del usuario (transaccional).
     * Borra todas las filas de la pivot y reinserta las nuevas.
     *
     * Si ya hay una transacción activa en la conexión (porque el caller —
     * `crear()` o `actualizar()` — abrió una), la respeta y NO inicia una
     * propia. Esto evita el "There is already an active transaction" que
     * tira PDO con `EMULATE_PREPARES=false`.
     *
     * @param array<int, string> $rolesUi UI keys del catálogo
     */
    public function asignarRoles(int $idUsuario, array $rolesUi): void
    {
        if (!$this->rolesUiValidos($rolesUi)) {
            throw new \InvalidArgumentException('Alguno de los roles seleccionados no existe en el catálogo.');
        }

        $transaccionPropia = !$this->db->inTransaction();
        if ($transaccionPropia) {
            $this->db->beginTransaction();
        }
        try {
            $del = $this->db->prepare('DELETE FROM usuario_roles WHERE id_usuario = :u');
            $del->execute(['u' => $idUsuario]);

            if (!empty($rolesUi)) {
                $tiposRolBd = array_map(
                    fn(string $ui) => self::ROL_UI_A_DB[$ui],
                    array_unique(array_map('strval', $rolesUi))
                );
                $placeholders = implode(',', array_fill(0, count($tiposRolBd), '?'));
                $sel = $this->db->prepare(
                    "SELECT id FROM roles WHERE tipo_rol IN ({$placeholders})"
                );
                $sel->execute($tiposRolBd);
                $idsRol = array_map(fn($r) => (int)$r['id'], $sel->fetchAll(PDO::FETCH_ASSOC));

                if (!empty($idsRol)) {
                    $ins = $this->db->prepare(
                        'INSERT INTO usuario_roles (id_usuario, id_rol) VALUES (:u, :r)'
                    );
                    foreach ($idsRol as $rid) {
                        $ins->execute(['u' => $idUsuario, 'r' => $rid]);
                    }
                }
            }

            if ($transaccionPropia) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($transaccionPropia && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Soft delete: marca `activo = FALSE`. El registro queda en la tabla
     * para preservar historial de traslados previos.
     */
    public function desactivar(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE usuarios SET activo = FALSE WHERE id = :id AND activo = TRUE'
        );
        $stmt->execute(['id' => $id]);
        if ($stmt->rowCount() === 0) {
            // idempotente: si ya estaba inactivo, no es error
            $existe = $this->buscarPorId($id);
            if (!$existe) {
                throw new \InvalidArgumentException("Usuario con id {$id} no encontrado.");
            }
            return;
        }
        $this->registrarAuditoria('ACTUALIZAR', 'usuarios', $id, ['activo' => false]);
    }

    /**
     * Reactiva un usuario (activo = TRUE). Idempotente.
     */
    public function reactivar(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE usuarios SET activo = TRUE WHERE id = :id AND activo = FALSE'
        );
        $stmt->execute(['id' => $id]);
        if ($stmt->rowCount() === 0) {
            $existe = $this->buscarPorId($id);
            if (!$existe) {
                throw new \InvalidArgumentException("Usuario con id {$id} no encontrado.");
            }
            return;
        }
        $this->registrarAuditoria('ACTUALIZAR', 'usuarios', $id, ['activo' => true]);
    }

    /**
     * Devuelve los UI keys de los roles de un usuario (ej: ['administrador']).
     * Usado por badges en la tabla y para popular checks en el form de edición.
     *
     * @return array<int, string>
     */
    public function obtenerRolesUiPorUsuario(int $idUsuario): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.tipo_rol
             FROM usuario_roles ur
             JOIN roles r ON ur.id_rol = r.id
             WHERE ur.id_usuario = :u
             ORDER BY r.tipo_rol'
        );
        $stmt->execute(['u' => $idUsuario]);
        $tiposBd = array_map(fn($r) => (string)$r['tipo_rol'], $stmt->fetchAll(PDO::FETCH_ASSOC));

        $uiKeys = [];
        foreach ($tiposBd as $t) {
            $ui = self::ROL_DB_A_UI[$t] ?? null;
            if ($ui !== null) {
                $uiKeys[] = $ui;
            }
        }
        return $uiKeys;
    }

    /**
     * Verifica si existe un usuario con ese email (sin importar id).
     */
    private function existeEmail(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM usuarios WHERE email = :e LIMIT 1');
        $stmt->execute(['e' => $email]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Verifica si existe un usuario con ese email, excluyendo al id dado
     * (usado en `actualizar()` para detectar colisión contra otros usuarios).
     */
    private function existeEmailExcepto(string $email, int $idExcluir): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM usuarios WHERE email = :e AND id <> :id LIMIT 1'
        );
        $stmt->execute(['e' => $email, 'id' => $idExcluir]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Verifica que todos los UI keys de roles sean válidos contra el catálogo.
     *
     * @param array<int, mixed> $rolesUi
     */
    private function rolesUiValidos(array $rolesUi): bool
    {
        foreach ($rolesUi as $r) {
            if (!is_string($r) || !isset(self::ROL_UI_A_DB[$r])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Recibe una fila cruda del SELECT y la decora con campos derivados
     * que consume la vista (roles_ui, roles_etiquetas, estado_legible).
     */
    private function hidratarFila(array $fila): array
    {
        $rolesBd = [];
        if (!empty($fila['roles_db'])) {
            $rolesBd = array_filter(explode(',', (string)$fila['roles_db']));
        }
        $rolesUi = [];
        foreach ($rolesBd as $bd) {
            $ui = self::ROL_DB_A_UI[$bd] ?? null;
            if ($ui !== null) {
                $rolesUi[] = $ui;
            }
        }

        $etiquetas = [];
        foreach (\Nucleo\Constantes\Roles::labels() as $clave => $label) {
            if (in_array($clave, $rolesUi, true)) {
                $etiquetas[$clave] = $label;
            }
        }

        $fila['id'] = (int)$fila['id'];
        $fila['ci'] = (int)$fila['ci'];
        $fila['activo'] = (bool)$fila['activo'];
        $fila['roles_ui'] = $rolesUi;
        $fila['roles_etiquetas'] = $etiquetas;
        $fila['estado_legible'] = $fila['activo'] ? 'Activo' : 'Inactivo';
        unset($fila['roles_db']);
        return $fila;
    }

    /**
     * Helper de auditoría — copy-paste literal de `ModeloTraslado`.
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
