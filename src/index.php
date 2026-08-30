<?php
// Lo utilizo para identificar errores * Pereyra*
//ini_set('display_errors', 1);
//error_reporting(E_ALL);

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

// ==========================================
// RUTAS PÚBLICAS (ACCESO POR QR - ISSUE # 110)
// ==========================================
$enrutador->get('/d/{slug}', [\Controladores\ControladorDocumentosPublico::class, 'categoriaPorSlug']);
$enrutador->get('/d/doc/{id}', [\Controladores\ControladorDocumentosPublico::class, 'verPorId']);

// RUTAS PÚBLICAS (ENCUESTAS)
$enrutador->get('/encuesta/gracias', [\Controladores\ControladorEncuestaPublica::class, 'gracias']);
$enrutador->get('/encuesta/{token}', [\Controladores\ControladorEncuestaPublica::class, 'mostrar']);
$enrutador->post('/encuesta/{token}/enviar', [\Controladores\ControladorEncuestaPublica::class, 'enviar']);

// Rutas de Autenticación
$enrutador->get('/login', [\Controladores\ControladorAuth::class, 'login']);
$enrutador->post('/login', [\Controladores\ControladorAuth::class, 'autenticar']);
$enrutador->get('/logout', [\Controladores\ControladorAuth::class, 'logout']);

// Página "sin acceso" para cuentas con sesión pero sin rol válido.
// Va en ControladorAuth (no protegido) para evitar loop con el guard.
$enrutador->get('/sin-acceso', [\Controladores\ControladorAuth::class, 'sinAcceso']);

// Cambio de contraseña obligatorio (#40 — usuario root recién creado).
$enrutador->get('/cambiar-password',  [\Controladores\ControladorAuth::class, 'cambiarPassword']);
$enrutador->post('/cambiar-password', [\Controladores\ControladorAuth::class, 'cambiarPasswordSubmit']);

// Rutas del Dashboard (Luego de autenticacion)
$enrutador->get('/dashboard/documentos', [\Controladores\ControladorDashboard::class, 'documentos']);
$enrutador->get('/dashboard/documentos/categoria/{slug}', [\Controladores\ControladorDashboard::class, 'documentosCategoria']);
$enrutador->post('/dashboard/documentos/crear', [\Controladores\ControladorDashboard::class, 'crearDocumento']);
$enrutador->get('/dashboard/traslados', [\Controladores\ControladorDashboard::class, 'trasladosInicio']);
$enrutador->get('/dashboard/traslados/nuevo', [\Controladores\ControladorDashboard::class, 'nuevoTraslado']);
$enrutador->get('/dashboard/traslados/{id}', [\Controladores\ControladorDashboard::class, 'detalleTraslado']);

// Encuestas
$enrutador->get('/dashboard/encuestas', [\Controladores\ControladorDashboard::class, 'encuestas']);
$enrutador->post('/dashboard/encuestas', [\Controladores\ControladorDashboard::class, 'encuestaSubmit']);

// Permisos (matriz)
$enrutador->get('/dashboard/permisos', [\Controladores\ControladorDashboard::class, 'permisos']);

// MÓDULO PERMISOS (API) — issue #130
$enrutador->post('/api/permisos/toggle', [\Controladores\ControladorDashboard::class, 'apiPermisoToggle']);
$enrutador->post('/api/permisos/batch',  [\Controladores\ControladorDashboard::class, 'apiPermisoBatch']);

// Usuarios (CRUD contra BD)
$enrutador->get('/dashboard/usuarios', [\Controladores\ControladorDashboard::class, 'usuarios']);
$enrutador->get('/dashboard/usuarios/nuevo', [\Controladores\ControladorDashboard::class, 'usuarioNuevo']);
$enrutador->post('/dashboard/usuarios', [\Controladores\ControladorDashboard::class, 'usuarioCrear']);
$enrutador->get('/dashboard/usuarios/{id}/editar', [\Controladores\ControladorDashboard::class, 'usuarioEditar']);
$enrutador->post('/dashboard/usuarios/{id}', [\Controladores\ControladorDashboard::class, 'usuarioActualizar']);
$enrutador->post('/dashboard/usuarios/{id}/baja', [\Controladores\ControladorDashboard::class, 'usuarioBaja']);
$enrutador->post('/dashboard/usuarios/{id}/reactivar', [\Controladores\ControladorDashboard::class, 'usuarioReactivar']);

// Vehículos (CRUD + liberación automática, issue #131)
$enrutador->get('/dashboard/vehiculos', [\Controladores\ControladorDashboard::class, 'vehiculos']);
$enrutador->get('/dashboard/vehiculos/nuevo', [\Controladores\ControladorDashboard::class, 'vehiculoNuevo']);
$enrutador->post('/dashboard/vehiculos', [\Controladores\ControladorDashboard::class, 'vehiculoCrear']);
$enrutador->get('/dashboard/vehiculos/{id}/editar', [\Controladores\ControladorDashboard::class, 'vehiculoEditar']);
$enrutador->post('/dashboard/vehiculos/{id}', [\Controladores\ControladorDashboard::class, 'vehiculoActualizar']);
$enrutador->post('/dashboard/vehiculos/{id}/baja', [\Controladores\ControladorDashboard::class, 'vehiculoBaja']);
$enrutador->post('/dashboard/vehiculos/{id}/reactivar', [\Controladores\ControladorDashboard::class, 'vehiculoReactivar']);

// Auditoría de logs
$enrutador->get('/dashboard/auditoria', [\Controladores\ControladorAuditoria::class, 'inicio']);

// Rutas API para traslados
$enrutador->get('/api/traslados/{id}', [\Controladores\ControladorDashboard::class, 'apiObtenerTraslado']);
$enrutador->post('/api/traslados', [\Controladores\ControladorDashboard::class, 'apiCrearTraslado']);
$enrutador->post('/api/traslados/{id}/salida', [\Controladores\ControladorDashboard::class, 'apiRegistrarSalida']);
$enrutador->post('/api/traslados/{id}/arribo', [\Controladores\ControladorDashboard::class, 'apiRegistrarArribo']);
$enrutador->post('/api/traslados/{id}/reportes', [\Controladores\ControladorDashboard::class, 'apiCrearReporte']);
$enrutador->post('/api/traslados/{id}/cancelar', [\Controladores\ControladorDashboard::class, 'apiCancelarTraslado']);

// Ubicaciones (destinos)
$enrutador->post('/api/ubicaciones', [\Controladores\ControladorDashboard::class, 'apiCrearUbicacion']);


// Ruta para la Página de Clientes (o Pacientes)
// $enrutador->get('/clientes', [\Controladores\ControladorCliente::class, 'inicio']);

// Pruebas con parametros
// $enrutador->get('/prueba', [\Controladores\ControladorDocumentos::class, 'prueba']);
// $enrutador->get('/prueba/{id}', [\Controladores\ControladorDocumentos::class, 'pruebaDetalle']);

// Rutas legacy /traslados/* eliminadas en feat/99 — el sidebar y todos los
// enlaces internos apuntan a /dashboard/traslados/*.

// Registrar endpoint del seeder
$enrutador->get('/seed', [\Controladores\ControladorSeed::class, 'ejecutar']);

// Ubicaciones (destinos)
$enrutador->post('/api/ubicaciones', [\Controladores\ControladorDashboard::class, 'apiCrearUbicacion']);

// Endpoint API POST para subida de documentos (#116) — ¡DEBE IR ANTES DE DESPACHAR!
$enrutador->post('/api/documentos', [\Controladores\ControladorDocumentos::class, 'subir']);

// Endpoints para actualizar / eliminar (soft delete) documentos.
$enrutador->post('/api/documentos/{id}',          [\Controladores\ControladorDocumentos::class, 'actualizar']);
$enrutador->post('/api/documentos/{id}/eliminar', [\Controladores\ControladorDocumentos::class, 'eliminar']);

// Registrar endpoint del seeder
$enrutador->get('/seed', [\Controladores\ControladorSeed::class, 'ejecutar']);

// 3. Ejecutamos el enrutador.
// Le pasamos el método (si es GET o POST) y la dirección que el usuario escribió en el navegador.
$metodo = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

$enrutador->despachar($metodo, $uri);