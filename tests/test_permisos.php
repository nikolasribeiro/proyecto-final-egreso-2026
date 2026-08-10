<?php

/**
 * Tests de humo para `ModeloPermiso` + `Roles::permiso()` — issue #130.
 *
 * Verifica:
 *  - Roles::permiso() devuelve el valor hardcoded cuando la BD está vacía
 *    (fallback).
 *  - ModeloPermiso::alternar() persiste en BD y devuelve diff correcto.
 *  - Después de alternar, Roles::permiso() refleja el nuevo valor
 *    (lectura desde BD, no desde el fallback).
 *  - alternar() rechaza combinaciones inválidas (rol/recurso/accion que
 *    no existen en Roles::matriz()).
 *  - alternarBatch() aplica varios toggles y todos quedan persistidos.
 *
 * Asume que el seed dev corrió y existe un usuario administrador con
 * id=1. Si no, los tests pueden fallar — re-ejecutar /seed primero.
 *
 * Estilo: igual a tests/test_integracion.php (script PHP plano, sin
 * PHPUnit). Ejecutar con:
 *
 *   docker exec -it songbird_web php tests/test_permisos.php
 */

require_once __DIR__ . '/../src/Nucleo/Conexion.php';
require_once __DIR__ . '/../src/Nucleo/Constantes/Roles.php';
require_once __DIR__ . '/../src/Modelos/ModeloPermiso.php';

use Modelos\ModeloPermiso;
use Nucleo\Conexion;
use Nucleo\Constantes\Roles;

echo "=== TESTS DE PERMISOS (#130) ===\n\n";

$pass = 0;
$fail = 0;

function assert_true(string $label, bool $cond): void
{
    global $pass, $fail;
    if ($cond) {
        echo "[OK]    {$label}\n";
        $pass++;
    } else {
        echo "[FAIL]  {$label}\n";
        $fail++;
    }
}

$db = Conexion::obtenerInstancia();

// Snapshot del estado antes de empezar, para restaurar al final y no
// dejar la matriz dirty entre runs.
$antes = $db->query('SELECT id_rol, recurso, accion, permitido FROM permisos_rol')
            ->fetchAll(PDO::FETCH_ASSOC);

// Forzar limpieza al final.
$cleanup = function () use ($db, $antes): void {
    $db->exec('DELETE FROM permisos_rol');
    if (!empty($antes)) {
        $stmt = $db->prepare(
            'INSERT INTO permisos_rol (id_rol, recurso, accion, permitido)
             VALUES (:r, :rec, :acc, :p)'
        );
        foreach ($antes as $f) {
            $stmt->execute([
                'r'   => (int)$f['id_rol'],
                'rec' => (string)$f['recurso'],
                'acc' => (string)$f['accion'],
                'p'   => (int)$f['permitido'],
            ]);
        }
    }
};

// 1. BD vacía → fallback hardcoded.
$db->exec('DELETE FROM permisos_rol');
Roles::invalidarCachePermisos();
assert_true(
    'BD vacía: Roles::permiso cae al hardcoded (admin ve traslados.ver)',
    Roles::permiso('administrador', 'traslados', 'ver') === true
);
assert_true(
    'BD vacía: medico NO ve permisos.editar',
    Roles::permiso('medico', 'permisos', 'editar') === false
);

// 2. alternar() persiste y Roles::permiso refleja el cambio.
$modelo = new ModeloPermiso();
$roles = $modelo->obtenerRoles();
$idRolAdmin = 0;
foreach ($roles as $id => $tipo) {
    if ($tipo === 'ADMINISTRATIVO') { $idRolAdmin = $id; break; }
}
assert_true('existe rol ADMINISTRATIVO en BD', $idRolAdmin > 0);

if ($idRolAdmin > 0) {
    // médico no ve permisos.editar (estado hardcoded). Lo negamos.
    $idRolMedico = 0;
    foreach ($roles as $id => $tipo) {
        if ($tipo === 'MEDICO') { $idRolMedico = $id; break; }
    }
    if ($idRolMedico > 0) {
        $diff = $modelo->alternar($idRolMedico, 'permisos', 'editar', true, $idRolAdmin);
        assert_true(
            'alternar() devuelve diff con antes=false, despues=true',
            $diff['antes'] === false && $diff['despues'] === true
        );
        // La próxima lectura de Roles::permiso debe venir de BD.
        Roles::invalidarCachePermisos(); // por si el modelo no lo invalidó
        assert_true(
            'Roles::permiso lee de BD después de alternar (medico ve permisos.editar)',
            Roles::permiso('medico', 'permisos', 'editar') === true
        );

        // 3. Reversión.
        $diff2 = $modelo->alternar($idRolMedico, 'permisos', 'editar', false, $idRolAdmin);
        assert_true(
            'alternar() reversión: antes=true, despues=false',
            $diff2['antes'] === true && $diff2['despues'] === false
        );
        Roles::invalidarCachePermisos();
        assert_true(
            'Roles::permiso refleja la reversión',
            Roles::permiso('medico', 'permisos', 'editar') === false
        );
    }
}

// 4. Combinación inválida se rechaza.
$threw = false;
try {
    $modelo->alternar($idRolAdmin, 'recurso_inexistente', 'ver', true, $idRolAdmin);
} catch (\InvalidArgumentException $e) {
    $threw = true;
}
assert_true('alternar() lanza InvalidArgumentException para recurso inexistente', $threw);

// 5. alternarBatch() aplica varios toggles.
if ($idRolAdmin > 0) {
    $toggles = [
        ['id_rol' => $idRolAdmin, 'recurso' => 'traslados',  'accion' => 'ver',       'permitido' => true],
        ['id_rol' => $idRolAdmin, 'recurso' => 'documentos', 'accion' => 'crear',     'permitido' => true],
    ];
    $diffs = $modelo->alternarBatch($toggles, $idRolAdmin);
    assert_true('alternarBatch devuelve diffs por toggle', count($diffs) === 2);

    $cnt = (int)$db->query(
        "SELECT COUNT(*) FROM permisos_rol
         WHERE id_rol = {$idRolAdmin}
           AND ((recurso = 'traslados' AND accion = 'ver')
             OR (recurso = 'documentos' AND accion = 'crear'))"
    )->fetchColumn();
    assert_true('alternarBatch persiste ambos toggles en BD', $cnt === 2);
}

// Cleanup: restaurar el estado previo.
$cleanup();

echo "\n";
echo "Pasaron:  {$pass}\n";
echo "Fallaron: {$fail}\n";
echo ($fail === 0)
    ? "✔ TODOS LOS TESTS DE PERMISOS PASARON.\n"
    : "✖ HAY TESTS DE PERMISOS FALLANDO.\n";

/*
docker exec -it songbird_web php tests/test_permisos.php
*/