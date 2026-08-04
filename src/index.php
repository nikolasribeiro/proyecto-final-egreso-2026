<?php
ini_set('display_errors', '0');

// Evita Clickjacking
header("X-Frame-Options: DENY");

// Evita MIME-Sniffing
header("X-Content-Type-Options: nosniff");

// Evita XSS
header("X-XSS-Protection: 1; mode=block");

// CSP (Content Security Policy)
header("Content-Security-Policy: default-src 'self' 'unsafe-inline'; img-src https://api.qrserver.com/v1/create-qr-code/; connect-src 'self' https://api.qrserver.com/v1/create-qr-code/;");

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


// Rutas de Autenticación
$enrutador->get('/login', [\Controladores\ControladorAuth::class, 'login']);
$enrutador->post('/login', [\Controladores\ControladorAuth::class, 'autenticar']);
$enrutador->get('/logout', [\Controladores\ControladorAuth::class, 'logout']);

// Rutas del Dashboard (Luego de autenticacion)
$enrutador->get('/dashboard/documentos', [\Controladores\ControladorDashboard::class, 'documentos']);
$enrutador->get('/dashboard/documentos/categoria/{slug}', [\Controladores\ControladorDashboard::class, 'documentosCategoria']);
$enrutador->get('/dashboard/traslados', [\Controladores\ControladorDashboard::class, 'trasladosInicio']);
$enrutador->get('/dashboard/traslados/nuevo', [\Controladores\ControladorDashboard::class, 'nuevoTraslado']);
$enrutador->get('/dashboard/traslados/{id}', [\Controladores\ControladorDashboard::class, 'detalleTraslado']);

// Encuestas
$enrutador->get('/dashboard/encuestas', [\Controladores\ControladorDashboard::class, 'encuestas']);
$enrutador->post('/dashboard/encuestas', [\Controladores\ControladorDashboard::class, 'encuestaSubmit']);

// Permisos (matriz)
$enrutador->get('/dashboard/permisos', [\Controladores\ControladorDashboard::class, 'permisos']);

// Usuarios (baja lógica)
$enrutador->get('/dashboard/usuarios', [\Controladores\ControladorDashboard::class, 'usuarios']);
$enrutador->post('/dashboard/usuarios/{username}/baja', [\Controladores\ControladorDashboard::class, 'usuarioBaja']);
$enrutador->post('/dashboard/usuarios/{username}/reactivar', [\Controladores\ControladorDashboard::class, 'usuarioReactivar']);

// Auditoría de logs
$enrutador->get('/dashboard/auditoria', [\Controladores\ControladorAuditoria::class, 'inicio']);

// Rutas API para traslados
$enrutador->get('/api/traslados/{id}', [\Controladores\ControladorDashboard::class, 'apiObtenerTraslado']);
$enrutador->post('/api/traslados/{id}/arribo', [\Controladores\ControladorDashboard::class, 'apiRegistrarArribo']);
$enrutador->post('/api/traslados/{id}/reportes', [\Controladores\ControladorDashboard::class, 'apiCrearReporte']);
$enrutador->post('/api/traslados/{id}/cancelar', [\Controladores\ControladorDashboard::class, 'apiCancelarTraslado']);


// Ruta para la Página de Clientes (o Pacientes)
// $enrutador->get('/clientes', [\Controladores\ControladorCliente::class, 'inicio']);

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
