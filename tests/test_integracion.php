<?php

require_once __DIR__ . '/../src/Nucleo/Conexion.php';
require_once __DIR__ . '/../src/Modelos/ModeloDocumento.php';
require_once __DIR__ . '/../src/Modelos/ModeloTraslado.php';

use Modelos\ModeloDocumento;
use Modelos\ModeloTraslado;

echo "=== INICIANDO PRUEBA DE INTEGRACIÓN S.I.G.S.M. ===\n\n";

try {
    // 1. Probar ModeloDocumento
    $modDoc = new ModeloDocumento();
    $docs = $modDoc->obtenerTodos();
    echo "[OK] ModeloDocumento conectado. Total documentos activos recuperados: " . count($docs) . "\n";

    // 2. Probar ModeloTraslado
    $modTraslado = new ModeloTraslado();
    $traslados = $modTraslado->obtenerTodos('todos', ['verde', 'amarillo', 'rojo']);
    echo "[OK] ModeloTraslado conectado. Total solicitudes de traslado: " . count($traslados) . "\n";

    echo "\n✔ PRUEBA DE INTEGRACIÓN EXITOSA: La capa de persistencia y controladores están listos.\n";
} catch (\Exception $e) {
    echo "\n✖ ERROR EN LA PRUEBA DE INTEGRACIÓN: " . $e->getMessage() . "\n";
}

/* Codigo para probar en el entorndo de Docker

docker exec -it songbird_app php tests/test_integracion.php

*/