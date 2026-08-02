<?php
ini_set('display_errors', '0');

// Evita Clickjacking
header("X-Frame-Options: DENY");

// Evita MIME-Sniffing
header("X-Content-Type-Options: nosniff");

// Evita XSS
header("X-XSS-Protection: 1; mode=block");

// CSP (Content Security Policy)
// Este es el que usaremos en PRODUCCION
//header("Content-Security-Policy: default-src 'self';");

// Este es el que usaremos en DESARROLLO
header("Content-Security-Policy: default-src 'self' 'unsafe-inline';");

spl_autoload_register(function ($nombre_clase) {
    $archivo = __DIR__ . '/' . str_replace('\\', '/', $nombre_clase) . '.php';
    if (file_exists($archivo)) {
        require_once $archivo;
    }
});

\Nucleo\Sesion::iniciar();

set_exception_handler(function (\Throwable $e) {
    error_log($e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());

    http_response_code(500);
    try {
        $controlador = new \Controladores\ControladorErrores();
        $controlador->errorServidor();
    } catch (\Throwable $e2) {
        error_log("FALLO EN CASCADA (Error Vista): " . $e2->getMessage());
    }
    exit;
});

// ==========================================
// INICIALIZACIÓN
// ==========================================
// Cargamos funciones de ayuda globales (ayudantes)
require_once __DIR__ . '/Nucleo/ayudantes.php';

// ==========================================
// ENRUTADOR CENTRAL
// ==========================================
// 1. Creamos el enrutador. Este objeto guardará las páginas (rutas) de nuestra web.
$enrutador = new \Nucleo\Enrutador();

// 2. Registramos las páginas. 
// Aquí definimos: (Método de envío, Dirección URL, [Nombre del Controlador, Nombre de la Función])

// Ruta para la Página de Inicio (Raíz)
$enrutador->get('/', [\Controladores\ControladorDocumentos::class, 'inicio']);

// Pruebas con parametros
// $enrutador->get('/prueba', [\Controladores\ControladorDocumentos::class, 'prueba']);
// $enrutador->get('/prueba/{id}', [\Controladores\ControladorDocumentos::class, 'pruebaDetalle']);

// Rutas del Módulo de Traslados (Ambulancias)
$enrutador->get('/traslados', [\Controladores\ControladorTraslados::class, 'inicio']);
$enrutador->get('/traslados/nuevo', [\Controladores\ControladorTraslados::class, 'nuevo']);
$enrutador->get('/traslados/detalle', [\Controladores\ControladorTraslados::class, 'detalle']);

// Acciones POST del Módulo de Traslados
$enrutador->post('/traslados/guardar', [\Controladores\ControladorTraslados::class, 'guardar']);
$enrutador->post('/traslados/actualizar-estado', [\Controladores\ControladorTraslados::class, 'actualizarEstado']);

// Registrar endpoint del seeder
$enrutador->get('/seed', [\Controladores\ControladorSeed::class, 'ejecutar']);

// 3. Ejecutamos el enrutador.
// Le pasamos el método (si es GET o POST) y la dirección que el usuario escribió en el navegador.
$metodo = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

$enrutador->despachar($metodo, $uri);
