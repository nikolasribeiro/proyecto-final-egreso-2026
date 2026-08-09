<?php

/**
 * Smoke-test del CRUD de usuarios (issue #127).
 *
 * Cubre:
 *   - Alta con CI/email únicos y roles múltiples
 *   - Validación de CI duplicado y email duplicado
 *   - Validación de email con formato inválido
 *   - Validación de roles inexistentes
 *   - Edición con CI inmutable (la API ignora el CI enviado)
 *   - Asignación múltiple de roles (la pivot refleja los UI keys)
 *   - Baja lógica (activo = FALSE) + reactivación (activo = TRUE)
 *   - Que `obtenerChoferesDisponibles()` y `obtenerEnfermeros()`
 *     lean desde la nueva fuente con filtro activo = TRUE y rol correcto.
 *   - Que `soporte_tecnico` NO aparezca en los selects operativos.
 *   - Auditoría: las mutaciones CUD insertan filas en `logs_auditoria`.
 *
 * Estilo: script PHP plano con echo, sin PHPUnit. Sigue el patrón de
 * `tests/test_integracion.php`. NO se ejecuta automáticamente: Nicolas
 * lo corre con `docker exec -it songbird_web php tests/test_usuarios.php`.
 *
 * Pre-requisito: el seeder (`/seed`) debe haberse corrido al menos una
 * vez, para tener roles y un admin con id conocido.
 */

require_once __DIR__ . '/../src/Nucleo/Conexion.php';
require_once __DIR__ . '/../src/Modelos/ModeloUsuario.php';
require_once __DIR__ . '/../src/Modelos/ModeloTraslado.php';

use Modelos\ModeloUsuario;
use Modelos\ModeloTraslado;
use Nucleo\Conexion;

echo "=== TEST INTEGRACIÓN: Gestión de Usuarios (#127) ===\n\n";

// Bandera global para que el helper de auditoría no falle por falta de
// sesión (los tests no loguean un usuario real).
$_SESSION = ['user' => ['id' => null]];

$errores = 0;
$ok = 0;

function assertTrue(bool $cond, string $msj): void
{
    global $errores, $ok;
    if ($cond) {
        echo "  [OK]   $msj\n";
        $ok++;
    } else {
        echo "  [FAIL] $msj\n";
        $errores++;
    }
}

function assertThrows(callable $fn, string $msjParcial, string $msjTest): void
{
    global $errores, $ok;
    try {
        $fn();
        echo "  [FAIL] $msjTest (no lanzó excepción)\n";
        $errores++;
    } catch (\InvalidArgumentException $e) {
        if (stripos($e->getMessage(), $msjParcial) !== false) {
            echo "  [OK]   $msjTest\n";
            $ok++;
        } else {
            echo "  [FAIL] $msjTest (mensaje inesperado: {$e->getMessage()})\n";
            $errores++;
        }
    } catch (\Throwable $e) {
        echo "  [FAIL] $msjTest (excepción equivocada: " . get_class($e) . ": {$e->getMessage()})\n";
        $errores++;
    }
}

try {
    $db = Conexion::obtenerInstancia();
    $modelo = new ModeloUsuario();
    $modeloTraslado = new ModeloTraslado();

    // Limpieza previa: borrar usuarios de CI >= 99000000 para que el test sea
    // idempotente (no romper los seeds si se corre varias veces).
    $db->exec("DELETE FROM logs_auditoria WHERE tabla_afectada IN ('usuarios') AND JSON_EXTRACT(detalles, '$.ci') >= 99000000");
    $db->exec("DELETE FROM usuario_roles WHERE id_usuario IN (SELECT id FROM usuarios WHERE ci >= 99000000)");
    $db->exec("DELETE FROM usuarios WHERE ci >= 99000000");

    // ----------------------------------------------------------------
    // 1. Listar — debe devolver al menos los 5 usuarios semilla
    // ----------------------------------------------------------------
    echo "\n[1] Listar usuarios\n";
    $todos = $modelo->listar('todos', '', '', 1, 100);
    assertTrue(count($todos) >= 5, "listar() devuelve al menos los 5 usuarios semilla (" . count($todos) . ")");
    $hayChofer = false;
    $hayEnfermero = false;
    foreach ($todos as $u) {
        if (in_array('enfermero', $u['roles_ui'] ?? [], true)) $hayEnfermero = true;
        // 'chofer' no es UI key directo, se modela como enfermero en Roles::labels.
        // El filtro operativo se hace en ModeloTraslado::obtenerChoferesDisponibles
    }
    assertTrue($hayEnfermero, "Hay al menos un usuario con rol 'enfermero'");

    // ----------------------------------------------------------------
    // 2. Alta válida
    // ----------------------------------------------------------------
    echo "\n[2] Alta válida\n";
    $ci = 99000001;
    $idNuevo = $modelo->crear([
        'ci' => $ci,
        'nombre' => 'Test Nombre',
        'apellido' => 'Test Apellido',
        'email' => 'test.alta@hospi.uy',
        'contrasena' => 'secret123',
        'roles' => ['enfermero'],
    ]);
    assertTrue($idNuevo > 0, "crear() devuelve un id > 0 (id={$idNuevo})");

    $usuario = $modelo->buscarPorId($idNuevo);
    assertTrue($usuario !== null, "buscarPorId() encuentra el usuario recién creado");
    assertTrue((int)$usuario['ci'] === $ci, "CI coincide con el dado de alta");
    assertTrue($usuario['activo'] === true, "activo = TRUE por defecto");
    assertTrue(!empty($usuario['fecha_alta']), "fecha_alta seteada automáticamente");
    assertTrue(in_array('enfermero', $usuario['roles'] ?? [], true), "Rol 'enfermero' asignado");

    // La contraseña debe estar hasheada (no en plano).
    $stmtHash = $db->prepare('SELECT contrasena FROM usuarios WHERE id = :id');
    $stmtHash->execute(['id' => $idNuevo]);
    $hashGuardado = (string)$stmtHash->fetchColumn();
    assertTrue(password_verify('secret123', $hashGuardado), "Contraseña almacenada como hash bcrypt");
    assertTrue($hashGuardado !== 'secret123', "Contraseña NO se guarda en plano");

    // ----------------------------------------------------------------
    // 3. Validación de unicidad
    // ----------------------------------------------------------------
    echo "\n[3] Validación de unicidad (CI y email)\n";
    assertThrows(
        fn() => $modelo->crear([
            'ci' => $ci,
            'nombre' => 'Otro', 'apellido' => 'Duplicado',
            'email' => 'otro@hospi.uy',
            'contrasena' => 'secret123',
            'roles' => ['administrador'],
        ]),
        'CI',
        "crear() rechaza CI duplicado con InvalidArgumentException"
    );
    assertThrows(
        fn() => $modelo->crear([
            'ci' => 99000002,
            'nombre' => 'Otro', 'apellido' => 'EmailDuplicado',
            'email' => 'test.alta@hospi.uy',
            'contrasena' => 'secret123',
            'roles' => ['administrador'],
        ]),
        'email',
        "crear() rechaza email duplicado"
    );
    assertThrows(
        fn() => $modelo->crear([
            'ci' => 99000003,
            'nombre' => 'X', 'apellido' => 'Y',
            'email' => 'no-es-un-email',
            'contrasena' => 'secret123',
            'roles' => [],
        ]),
        'inválido',
        "crear() rechaza email con formato inválido"
    );

    // ----------------------------------------------------------------
    // 4. Edición con CI inmutable
    // ----------------------------------------------------------------
    echo "\n[4] Edición con CI inmutable\n";
    $modelo->actualizar($idNuevo, [
        'nombre' => 'Nombre Editado',
        'apellido' => 'Apellido Editado',
        'email' => 'editado@hospi.uy',
        'roles' => ['administrador', 'enfermero'],
    ]);
    $editado = $modelo->buscarPorId($idNuevo);
    assertTrue($editado['nombre'] === 'Nombre Editado', "Edición modifica el nombre");
    assertTrue($editado['apellido'] === 'Apellido Editado', "Edición modifica el apellido");
    assertTrue($editado['email'] === 'editado@hospi.uy', "Edición modifica el email");
    assertTrue((int)$editado['ci'] === $ci, "La CI no se modifica (sigue siendo {$ci})");
    assertTrue(
        in_array('administrador', $editado['roles'], true) && in_array('enfermero', $editado['roles'], true),
        "Asignación múltiple de roles: administrador + enfermero"
    );

    // Email duplicado en edición
    assertThrows(
        fn() => $modelo->actualizar($idNuevo, [
            'email' => 'admin@hospital.com', // existe en seed
        ]),
        'email',
        "actualizar() rechaza email duplicado contra otro usuario"
    );

    // ----------------------------------------------------------------
    // 5. Roles inexistentes en catálogo
    // ----------------------------------------------------------------
    echo "\n[5] Validación de roles del catálogo\n";
    assertThrows(
        fn() => $modelo->crear([
            'ci' => 99000010, 'nombre' => 'X', 'apellido' => 'Y',
            'email' => 'rolfake@hospi.uy', 'contrasena' => 'secret123',
            'roles' => ['rol_inexistente'],
        ]),
        'catálogo',
        "crear() rechaza rol inexistente"
    );

    // ----------------------------------------------------------------
    // 6. Baja lógica y reactivación
    // ----------------------------------------------------------------
    echo "\n[6] Baja lógica y reactivación\n";
    $modelo->desactivar($idNuevo);
    $baja = $modelo->buscarPorId($idNuevo);
    assertTrue($baja['activo'] === false, "desactivar() setea activo = FALSE");

    // Verificar que sigue en la tabla (no DELETE físico)
    $existeStmt = $db->prepare('SELECT COUNT(*) FROM usuarios WHERE id = :id');
    $existeStmt->execute(['id' => $idNuevo]);
    $sigue = (int)$existeStmt->fetchColumn();
    assertTrue($sigue === 1, "El usuario sigue en la tabla tras baja lógica (no se borra)");

    // Idempotente: segunda baja no rompe
    $modelo->desactivar($idNuevo);
    assertTrue(true, "desactivar() es idempotente (segunda llamada no rompe)");

    $modelo->reactivar($idNuevo);
    $reactivado = $modelo->buscarPorId($idNuevo);
    assertTrue($reactivado['activo'] === true, "reactivar() setea activo = TRUE");

    // ----------------------------------------------------------------
    // 7. Filtros server-side
    // ----------------------------------------------------------------
    echo "\n[7] Filtros server-side\n";
    $filtroRol = $modelo->listar('todos', 'administrador', '', 1, 100);
    $todosTienenAdmin = true;
    foreach ($filtroRol as $u) {
        if (!in_array('administrador', $u['roles_ui'] ?? [], true)) {
            $todosTienenAdmin = false;
            break;
        }
    }
    assertTrue($todosTienenAdmin, "Filtro por rol='administrador' devuelve solo admins");
    assertTrue(count($filtroRol) >= 1, "Hay al menos 1 admin en la BD");

    $filtroQ = $modelo->listar('todos', '', 'editado', 1, 100);
    $matches = false;
    foreach ($filtroQ as $u) {
        if (stripos(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? '') . ' ' . ($u['email'] ?? ''), 'editado') !== false) {
            $matches = true;
            break;
        }
    }
    assertTrue($matches, "Filtro libre por texto 'editado' encuentra match");

    $filtroEstado = $modelo->listar('activos', '', '', 1, 100);
    $soloActivos = true;
    foreach ($filtroEstado as $u) {
        if (!$u['activo']) { $soloActivos = false; break; }
    }
    assertTrue($soloActivos, "Filtro estado='activos' devuelve solo activos");

    // ----------------------------------------------------------------
    // 8. ModeloTraslado lee desde la nueva fuente
    // ----------------------------------------------------------------
    echo "\n[8] ModeloTraslado: choferes/enfermeros desde la BD\n";
    $choferes = $modeloTraslado->obtenerChoferesDisponibles();
    assertTrue(is_array($choferes), "obtenerChoferesDisponibles() devuelve array");
    assertTrue(count($choferes) >= 1, "Hay al menos 1 chofer disponible (semilla o creado)");

    $enfermeros = $modeloTraslado->obtenerEnfermeros();
    assertTrue(count($enfermeros) >= 1, "Hay al menos 1 enfermero disponible (semilla o creado)");

    // soporte_tecnico NO debe aparecer en selects operativos
    // Creamos un soporte y verificamos que NO aparece ni en choferes ni en enfermeros.
    $idSoporte = $modelo->crear([
        'ci' => 99000020, 'nombre' => 'Sop', 'apellido' => 'Te',
        'email' => 'sop.test@hospi.uy', 'contrasena' => 'secret123',
        'roles' => ['soporte_tecnico'],
    ]);
    $choferesDespues = array_column($modeloTraslado->obtenerChoferesDisponibles(), 'ci');
    $enfermerosDespues = array_column($modeloTraslado->obtenerEnfermeros(), 'ci');
    assertTrue(!in_array(99000020, $choferesDespues, true), "soporte_tecnico NO aparece en choferes");
    assertTrue(!in_array(99000020, $enfermerosDespues, true), "soporte_tecnico NO aparece en enfermeros");

    // Un usuario dado de baja con rol operativo tampoco debe aparecer
    // (filtro activo = TRUE).
    $modelo->desactivar($idNuevo);
    $enfermerosDespBaja = array_column($modeloTraslado->obtenerEnfermeros(), 'ci');
    assertTrue(!in_array($ci, $enfermerosDespBaja, true), "Usuario dado de baja NO aparece en enfermeros");

    // ----------------------------------------------------------------
    // 9. Auditoría: las mutaciones CUD se registran en logs_auditoria
    // ----------------------------------------------------------------
    echo "\n[9] Auditoría: mutaciones CUD quedan registradas\n";
    $audStmt = $db->prepare(
        "SELECT accion, tabla_afectada, registro_id
         FROM logs_auditoria
         WHERE tabla_afectada = 'usuarios' AND registro_id = :id
         ORDER BY id ASC"
    );
    $audStmt->execute(['id' => $idNuevo]);
    $logs = $audStmt->fetchAll(\PDO::FETCH_ASSOC);
    $acciones = array_column($logs, 'accion');
    assertTrue(in_array('CREAR', $acciones, true), "Alta registrada como CREAR");
    assertTrue(in_array('ACTUALIZAR', $acciones, true), "Edición registrada como ACTUALIZAR");

    // ----------------------------------------------------------------
    // 10. Soft delete no rompe historial de traslados
    // ----------------------------------------------------------------
    echo "\n[10] Soft delete: historial de traslados preservado\n";
    // El chofer 33333333 del seed tiene traslados previos; desactivarlo no debe
    // romper la consulta histórica.
    $choferSeed = $modelo->buscarPorCi(33333333);
    if ($choferSeed !== null) {
        $modelo->desactivar((int)$choferSeed['id']);
        $trasladosStmt = $db->prepare(
            "SELECT COUNT(*) AS total FROM solicitud_traslados WHERE ci_chofer = :ci"
        );
        $trasladosStmt->execute(['ci' => 33333333]);
        $totalTraslados = (int)$trasladosStmt->fetch()['total'];
        assertTrue($totalTraslados >= 1, "Los traslados del chofer desactivado siguen consultables ({$totalTraslados})");
        // Reactivar para no contaminar el estado del resto de tests
        $modelo->reactivar((int)$choferSeed['id']);
    } else {
        echo "  [SKIP] Chofer seed 33333333 no existe, no se prueba soft delete histórico.\n";
    }

    // Limpieza final
    $db->exec("DELETE FROM logs_auditoria WHERE tabla_afectada IN ('usuarios') AND JSON_EXTRACT(detalles, '$.ci') >= 99000000");
    $db->exec("DELETE FROM usuario_roles WHERE id_usuario IN (SELECT id FROM usuarios WHERE ci >= 99000000)");
    $db->exec("DELETE FROM usuarios WHERE ci >= 99000000");

    echo "\n=== RESUMEN ===\n";
    echo "OK:    $ok\n";
    echo "FAIL:  $errores\n";
    if ($errores === 0) {
        echo "\n✔ TODOS LOS TESTS DE GESTIÓN DE USUARIOS PASARON.\n";
        exit(0);
    }
    echo "\n✖ HUBO $errores FALLAS — revisar arriba.\n";
    exit(1);
} catch (\Throwable $e) {
    echo "\n✖ ERROR FATAL EN EL TEST: " . $e->getMessage() . "\n";
    echo "  en " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

/* Codigo para probar en el entorno Docker

docker exec -it songbird_web php tests/test_usuarios.php

*/
