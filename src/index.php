<?php
ini_set('display_errors', '0');

// Evita Clickjacking
header("X-Frame-Options: DENY");

// Evita MIME-Sniffing
header("X-Content-Type-Options: nosniff");

// Evita XSS
header("X-XSS-Protection: 1; mode=block");

// CSP (Content Security Policy)
header("Content-Security-Policy: default-src 'self' 'unsafe-inline'; img-src https://api.qrserver.com/v1/create-qr-code/;");

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
$enrutador->get('/', [\Controladores\ControladorInicio::class, 'inicio']);

// Rutas de Autenticación
$enrutador->get('/login', [\Controladores\ControladorAuth::class, 'login']);
$enrutador->post('/login', [\Controladores\ControladorAuth::class, 'autenticar']);
$enrutador->get('/logout', [\Controladores\ControladorAuth::class, 'logout']);

// Rutas del Dashboard (Luego de autenticacion)
$enrutador->get('/dashboard/documentos', [\Controladores\ControladorDashboard::class, 'documentos']);
$enrutador->get('/dashboard/traslados', [\Controladores\ControladorDashboard::class, 'trasladosInicio']);


// Ruta para la Página de Clientes (o Pacientes)
$enrutador->get('/clientes', [\Controladores\ControladorCliente::class, 'inicio']);

// Pruebas con parametros
$enrutador->get('/prueba', [\Controladores\ControladorInicio::class, 'prueba']);
$enrutador->get('/prueba/{id}', [\Controladores\ControladorInicio::class, 'pruebaDetalle']);


// 3. Ejecutamos el enrutador.
// Le pasamos el método (si es GET o POST) y la dirección que el usuario escribió en el navegador.
$metodo = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

$enrutador->despachar($metodo, $uri);
