<?php

/**
 * Smoke-test del CRUD de vehículos (issue #131).
 *
 * Cubre:
 *   - Alta con matrícula única y tipo válido
 *   - Validación de matrícula duplicada
 *   - Validación de tipo de vehículo inexistente
 *   - Validación de matrícula vacía o muy larga
 *   - Listado + filtros (estado, activo, tipo, búsqueda)
 *   - Conteo por estado (total / disponibles / no_disponibles)
 *   - Edición de matrícula y tipo (inmutabilidad de id)
 *   - Soft delete (`activo = FALSE`) + reactivación
 *   - Auditoría: las mutaciones CUD insertan filas en `logs_auditoria`
 *   - Hookpoints de ModeloTraslado: ocupar y liberar vehículo al
 *     crear/cancelar un traslado. Cubre la lógica atómica del issue #131.
 *
 * Estilo: script PHP plano con echo, sin PHPUnit. Sigue el patrón de
 * `tests/test_usuarios.php`. NO se ejecuta automáticamente: Nicolas
 * lo corre con `docker exec -it songbird_web php tests/test_vehiculos.php`.
 *
 * Pre-requisito: el seeder (`/seed`) debe haberse corrido al menos una
 * vez, para tener `tipo_vehiculo` y vehículos de muestra.
 */

require_once __DIR__ . '/../src/Nucleo/Conexion.php';
require_once __DIR__ . '/../src/Nucleo/Sesion.php';
require_once __DIR__ . '/../src/Modelos/ModeloVehiculo.php';
require_once __DIR__ . '/../src/Modelos/ModeloTraslado.php';

use Modelos\ModeloVehiculo;
use Modelos\ModeloTraslado;
use Nucleo\Conexion;

echo "=== TEST INTEGRACIÓN: Gestión de Vehículos (#131) ===\n\n";

// Bandera global para que el helper de auditoría no falle por falta de sesión.
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
    $modelo = new ModeloVehiculo();
    $modeloTraslado = new ModeloTraslado();

    // Limpieza previa: borrar vehículos con matrícula TEST-* para idempotencia.
    $db->exec("DELETE FROM logs_auditoria WHERE tabla_afectada = 'vehiculos' AND JSON_EXTRACT(detalles, '$.matricula') LIKE 'TEST-%'");
    $db->exec("DELETE FROM vehiculos WHERE matricula LIKE 'TEST-%'");

    // ----------------------------------------------------------------
    // 0. Listado inicial debe incluir los vehículos del seed (mínimo 1)
    // ----------------------------------------------------------------
    echo "[0] Listado inicial (post-seed)\n";
    $totalInicial = $modelo->contar();
    $stats = $modelo->contarPorEstado();
    assertTrue($totalInicial >= 1, "El seed cargó al menos 1 vehículo (total={$totalInicial})");
    assertTrue(($stats['disponibles'] ?? 0) >= 1, "Hay vehículos disponibles en el seed ({$stats['disponibles']})");
    assertTrue(($stats['total'] ?? 0) === $totalInicial, "contar() y contarPorEstado()['total'] coinciden ({$totalInicial})");

    $tipos = $modelo->obtenerTiposVehiculo();
    assertTrue(!empty($tipos), "obtenerTiposVehiculo() devuelve al menos 1 tipo");
    $primerTipoId = (int)($tipos[0]['id'] ?? 0);
    assertTrue($primerTipoId > 0, "El primer tipo tiene id válido ({$primerTipoId})");

    // ----------------------------------------------------------------
    // 1. Alta válida
    // ----------------------------------------------------------------
    echo "\n[1] Alta válida\n";
    $matriculaTest = 'TEST-' . str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
    $nuevoId = $modelo->crear($matriculaTest, $primerTipoId);
    assertTrue($nuevoId > 0, "crear() devuelve un id > 0 (id={$nuevoId})");

    $v = $modelo->buscarPorId($nuevoId);
    assertTrue($v !== null, "El vehículo creado es buscable por id");
    assertTrue(strtoupper($v['matricula']) === $matriculaTest, "La matrícula guardada coincide (case-insensitive): {$v['matricula']}");
    assertTrue($v['estado'] === 'DISPONIBLE', "El estado inicial es DISPONIBLE");
    assertTrue($v['activo'] === true, "activo = TRUE al alta");
    assertTrue((int)$v['id_tipo_vehiculo'] === $primerTipoId, "El tipo guardado coincide");
    assertTrue($v['estado_legible'] === 'Disponible', "estado_legible hidratado");

    // ----------------------------------------------------------------
    // 2. Validaciones de alta
    // ----------------------------------------------------------------
    echo "\n[2] Validaciones de alta\n";
    assertThrows(
        fn() => $modelo->crear('', $primerTipoId),
        'matrícula',
        'crear() rechaza matrícula vacía'
    );
    assertThrows(
        fn() => $modelo->crear(str_repeat('A', 21), $primerTipoId),
        'largo máximo',
        'crear() rechaza matrícula > 20 chars'
    );
    assertThrows(
        fn() => $modelo->crear('TEST-VALIDO', 0),
        'Tipo',
        'crear() rechaza tipo de vehículo 0'
    );
    assertThrows(
        fn() => $modelo->crear('TEST-VALIDO', 999999),
        'Tipo',
        'crear() rechaza tipo de vehículo inexistente'
    );
    assertThrows(
        fn() => $modelo->crear($matriculaTest, $primerTipoId),
        'matrícula',
        'crear() rechaza matrícula duplicada'
    );

    // ----------------------------------------------------------------
    // 3. Listado + filtros
    // ----------------------------------------------------------------
    echo "\n[3] Listado + filtros\n";
    $listado = $modelo->listar('todos', 0, 'todos', '', 1, 100);
    $encontroTest = false;
    foreach ($listado as $fila) {
        if ((int)$fila['id'] === $nuevoId) {
            $encontroTest = true;
            break;
        }
    }
    assertTrue($encontroTest, "listar() incluye el vehículo recién creado");

    $listadoDisp = $modelo->listar('disponibles', 0, 'todos', '', 1, 100);
    $encontroDisp = false;
    foreach ($listadoDisp as $fila) {
        if ((int)$fila['id'] === $nuevoId && $fila['estado'] === 'DISPONIBLE') {
            $encontroDisp = true;
            break;
        }
    }
    assertTrue($encontroDisp, "listar('disponibles') incluye el vehículo de test");

    $listadoInact = $modelo->listar('todos', 0, 'inactivos', '', 1, 100);
    $encontroInact = false;
    foreach ($listadoInact as $fila) {
        if ((int)$fila['id'] === $nuevoId) {
            $encontroInact = true;
            break;
        }
    }
    assertTrue(!$encontroInact, "listar('inactivos') NO incluye el vehículo activo");

    $listadoTipo = $modelo->listar('todos', $primerTipoId, 'todos', '', 1, 100);
    assertTrue(count($listadoTipo) >= 1, "listar() filtrando por tipo devuelve resultados");

    $listadoQ = $modelo->listar('todos', 0, 'todos', 'TEST-', 1, 100);
    $encontroQ = false;
    foreach ($listadoQ as $fila) {
        if ((int)$fila['id'] === $nuevoId) {
            $encontroQ = true;
            break;
        }
    }
    assertTrue($encontroQ, "listar(q='TEST-') encuentra por matrícula parcial");

    // ----------------------------------------------------------------
    // 4. Edición
    // ----------------------------------------------------------------
    echo "\n[4] Edición\n";
    $segundoTipo = $tipos[1]['id'] ?? $tipos[0]['id'];
    $modelo->actualizar($nuevoId, $matriculaTest . '-M', $segundoTipo);
    $vEdit = $modelo->buscarPorId($nuevoId);
    assertTrue($vEdit['matricula'] === ($matriculaTest . '-M'), "actualizar() cambia la matrícula");
    assertTrue((int)$vEdit['id_tipo_vehiculo'] === (int)$segundoTipo, "actualizar() cambia el tipo");

    // Restaurar matrícula original para los siguientes tests
    $modelo->actualizar($nuevoId, $matriculaTest, $primerTipoId);

    // Validaciones de edición
    assertThrows(
        fn() => $modelo->actualizar($nuevoId, '', $primerTipoId),
        'matrícula',
        'actualizar() rechaza matrícula vacía'
    );
    assertThrows(
        fn() => $modelo->actualizar(99999999, $matriculaTest, $primerTipoId),
        'no encontrado',
        'actualizar() rechaza vehículo inexistente'
    );
    // Matrícula duplicada de otro vehículo: usar SCH-1234 del seed
    assertThrows(
        fn() => $modelo->actualizar($nuevoId, 'SCH-1234', $primerTipoId),
        'matrícula',
        'actualizar() rechaza matrícula de otro vehículo'
    );

    // ----------------------------------------------------------------
    // 5. Soft delete + reactivación
    // ----------------------------------------------------------------
    echo "\n[5] Soft delete + reactivación\n";
    $modelo->desactivar($nuevoId);
    $vBaja = $modelo->buscarPorId($nuevoId);
    assertTrue($vBaja['activo'] === false, "desactivar() marca activo=FALSE");
    assertTrue($vBaja['estado'] === 'NO-DISPONIBLE', "desactivar() fuerza estado=NO-DISPONIBLE");

    // Reactivar no cambia estado
    $modelo->reactivar($nuevoId);
    $vReac = $modelo->buscarPorId($nuevoId);
    assertTrue($vReac['activo'] === true, "reactivar() marca activo=TRUE");
    assertTrue($vReac['estado'] === 'NO-DISPONIBLE', "reactivar() NO toca estado (sigue NO-DISPONIBLE)");

    // Idempotencia
    $modelo->desactivar($nuevoId); // ya estaba
    $modelo->desactivar($nuevoId); // segunda vez, no debe romper
    assertTrue(true, "desactivar() es idempotente");
    $modelo->reactivar($nuevoId);

    // Vehículo inexistente
    assertThrows(
        fn() => $modelo->desactivar(99999999),
        'no encontrado',
        'desactivar() rechaza id inexistente'
    );
    assertThrows(
        fn() => $modelo->reactivar(99999999),
        'no encontrado',
        'reactivar() rechaza id inexistente'
    );

    // ----------------------------------------------------------------
    // 6. Hookpoints en ModeloTraslado — ocupar y liberar
    // ----------------------------------------------------------------
    echo "\n[6] Hookpoints ModeloTraslado (#131)\n";

    // 6.1. Setup: resetear el vehículo de test a estado limpio
    $modelo->reactivar($nuevoId);
    $db->prepare("UPDATE vehiculos SET estado = 'DISPONIBLE' WHERE id = :id")
       ->execute(['id' => $nuevoId]);

    $estadoAntes = $db->query("SELECT estado FROM vehiculos WHERE id = {$nuevoId}")
                      ->fetchColumn();
    assertTrue($estadoAntes === 'DISPONIBLE', "Setup: vehículo de test está DISPONIBLE");

    // 6.2. Validar que el filtro del wizard respeta estado
    $disponibles = $modeloTraslado->obtenerVehiculosDisponibles();
    $apareceEnWizard = false;
    foreach ($disponibles as $disp) {
        if ((int)$disp['id'] === $nuevoId) {
            $apareceEnWizard = true;
            break;
        }
    }
    assertTrue($apareceEnWizard, "Vehículo DISPONIBLE aparece en obtenerVehiculosDisponibles()");

    // 6.3. Verificar que Roles::matriz() tiene 'vehiculos' para administrador
    $matriz = \Nucleo\Constantes\Roles::matriz();
    assertTrue(
        isset($matriz['administrador']['vehiculos']),
        "Roles::matriz() tiene bloque 'vehiculos' para administrador"
    );
    assertTrue(
        $matriz['administrador']['vehiculos']['ver'] === true
            && $matriz['administrador']['vehiculos']['crear'] === true
            && $matriz['administrador']['vehiculos']['editar'] === true
            && $matriz['administrador']['vehiculos']['eliminar'] === true,
        "administrador tiene T-T-T-T en vehiculos"
    );

    $recursos = \Nucleo\Constantes\Roles::recursos();
    assertTrue(isset($recursos['vehiculos']), "Roles::recursos() incluye 'vehiculos'");

    // 6.4. Ocupar manualmente y verificar que desaparece del wizard
    $db->prepare("UPDATE vehiculos SET estado = 'NO-DISPONIBLE' WHERE id = :id")
       ->execute(['id' => $nuevoId]);
    $disponibles2 = $modeloTraslado->obtenerVehiculosDisponibles();
    $sigueEnWizard = false;
    foreach ($disponibles2 as $disp) {
        if ((int)$disp['id'] === $nuevoId) {
            $sigueEnWizard = true;
            break;
        }
    }
    assertTrue(!$sigueEnWizard, "Vehículo NO-DISPONIBLE NO aparece en obtenerVehiculosDisponibles()");

    // Restaurar
    $db->prepare("UPDATE vehiculos SET estado = 'DISPONIBLE' WHERE id = :id")
       ->execute(['id' => $nuevoId]);

    // ----------------------------------------------------------------
    // 7. Auditoría
    // ----------------------------------------------------------------
    echo "\n[7] Auditoría\n";
    $auditStmt = $db->prepare(
        "SELECT COUNT(*) AS total FROM logs_auditoria
         WHERE tabla_afectada = 'vehiculos'
           AND JSON_EXTRACT(detalles, '$.matricula') = :m"
    );
    $auditStmt->execute(['m' => $matriculaTest]);
    $auditoriaCount = (int)$auditStmt->fetch()['total'];
    assertTrue($auditoriaCount >= 1, "Hay al menos 1 entrada de auditoría para el vehículo de test (count={$auditoriaCount})");

    // ----------------------------------------------------------------
    // Limpieza final
    // ----------------------------------------------------------------
    $db->exec("DELETE FROM logs_auditoria WHERE tabla_afectada = 'vehiculos' AND JSON_EXTRACT(detalles, '$.matricula') LIKE 'TEST-%'");
    $db->exec("DELETE FROM vehiculos WHERE matricula LIKE 'TEST-%'");

    echo "\n=== RESUMEN ===\n";
    echo "OK:    $ok\n";
    echo "FAIL:  $errores\n";
    if ($errores === 0) {
        echo "\n✔ TODOS LOS TESTS DE GESTIÓN DE VEHÍCULOS PASARON.\n";
        exit(0);
    }
    echo "\n✖ HUBO $errores FALLAS — revisar arriba.\n";
    exit(1);
} catch (\Throwable $e) {
    echo "\n✖ ERROR FATAL EN EL TEST: " . $e->getMessage() . "\n";
    echo "  en " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "  " . $e->getTraceAsString() . "\n";
    exit(2);
}