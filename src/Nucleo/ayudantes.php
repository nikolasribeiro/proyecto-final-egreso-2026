<?php

declare(strict_types=1);

use Nucleo\Sesion;
use Nucleo\Constantes\Roles;
use Nucleo\Vista;

/**
 * Escapa strings (texto) para prevenir ataques XSS (Cross-Site Scripting).
 * Obligatorio usarlo en las vistas para cualquier variable dinámica.
 * 
 * @param string|null $string El texto a escapar
 * @return string El texto seguro y escapado
 */
function e(?string $string): string
{
    if ($string === null) {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Atajo simple para mostrar una vista sin necesidad de importar la clase Vista
 * en cada Controlador.
 * 
 * @param string $plantilla Ruta de la vista
 * @param array $datos Variables para la vista
 * @param string $plantillaBase El layout a utilizar
 */
function vista(string $plantilla, array $datos = [], ?string $plantillaBase = null): void
{
    Vista::mostrar($plantilla, $datos, $plantillaBase);
}

/**
 * Detiene la ejecución y envía una cabecera HTTP de redirección al navegador.
 *
 * @param string $url URL destino
 */
function redirigir(string $url): void
{
    header("Location: $url");
    exit;
}

/**
 * Detiene la ejecución, envía un código de error HTTP y muestra la vista de error.
 * 
 * @param int $codigo Código HTTP (ej: 404, 500)
 */
function abortar(int $codigo = 404): void
{
    http_response_code($codigo);

    try {
        if ($codigo === 404) {
            $controlador = new \Controladores\ControladorErrores();
            $controlador->noEncontrado();
        } elseif ($codigo === 403) {
            $controlador = new \Controladores\ControladorErrores();
            $controlador->prohibido();
        } elseif ($codigo === 500) {
            $controlador = new \Controladores\ControladorErrores();
            $controlador->errorServidor();
        } else {
            echo "Error $codigo";
        }
    } catch (\Throwable $e) {
        // En caso de que falle incluso la vista de error
        echo "Error Crítico: " . $codigo;
    }

    exit;
}

/**
 * Renderiza un componente visual de forma aislada.
 * * @param string $ruta Ruta relativa a la carpeta Componentes (ej: 'sidebar/sidebar')
 * @param array $datos Variables inyectadas al componente
 */
function componente(string $ruta, array $datos = []): void
{
    // Extrae las variables para que vivan solo dentro de este componente
    extract($datos);

    // Resuelve la ruta absoluta partiendo desde el núcleo hacia la carpeta de Vistas
    $archivo = dirname(__DIR__) . '/Componentes/' . $ruta . '.php';

    if (file_exists($archivo)) {
        require $archivo;
    } else {
        // Manejo amigable de errores en la interfaz para no romper todo el layout
        echo "Componente: $ruta - Error al traer el componente.";
    }
}

/**
 * Devuelve true si la request actual apunta a un endpoint de API
 * (prefijo /api/). Se usa para alternar entre respuesta HTML y JSON
 * en errores de autenticación / autorización.
 *
 * Extraído de `Nucleo\RutaProtegida::requestEsApi()` para evitar
 * duplicación; ambos lugares consumen este helper.
 */
function requestEsApi(): bool
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($uri, PHP_URL_PATH) ?? '';
    return str_starts_with($path, '/api/');
}

/**
 * Devuelve el peso máximo (en bytes) que acepta el servidor para un archivo
 * subido, según la configuración de PHP (`upload_max_filesize`).
 *
 * Se usa para dos cosas:
 *   1. Pintar el límite en el modal de subida (el usuario ve el límite antes
 *      de elegir el archivo, no después de que el servidor lo rechace).
 *   2. Que el frontend valide el tamaño ANTES de subir: si el archivo ya
 *      excede el límite, se avisa al instante y no se desperdicia una
 *      subida que el servidor va a rechazar con 413.
 *
 * El valor se lee del `php.ini` real (el `Dockerfile` lo fija en 50M), así
 * que no hay que mantener un número duplicado en el JS.
 *
 * @return int Bytes; 0 si PHP no reporta un valor utilizable.
 */
function tamano_maximo_subida(): int
{
    $raw = (string)ini_get('upload_max_filesize');
    if ($raw === '') {
        return 0;
    }

    $raw = trim($raw);
    $multiplicador = 1;

    // PHP acepta sufijos: K, M, G (y los variantes binarios KB/MB/GB).
    $ultimo = strtoupper(substr($raw, -1));
    if ($ultimo === 'K') {
        $multiplicador = 1024;
    } elseif ($ultimo === 'M') {
        $multiplicador = 1024 * 1024;
    } elseif ($ultimo === 'G') {
        $multiplicador = 1024 * 1024 * 1024;
    }

    $numero = (float)preg_replace('/[^0-9.]/', '', $raw);

    if ($numero <= 0) {
        return 0;
    }

    return (int)($numero * $multiplicador);
}

/**
 * Formatea un tamaño en bytes como texto legible ("50 MB", "1.5 MB").
 *
 * @param int $bytes
 * @return string
 */
function formatear_tamano(int $bytes): string
{
    if ($bytes <= 0) {
        return '';
    }

    $mb = $bytes / (1024 * 1024);

    if ($mb >= 1 && fmod($mb, 1.0) === 0.0) {
        return (string)(int)$mb . ' MB';
    }

    return number_format($mb, 1, ',', '.') . ' MB';
}

/**
 * Guard server-side de permisos a nivel de método.
 *
 * Cierra el agujero de autorización por el que un usuario con sesión
 * activa pero sin el permiso requerido para el recurso podía tipear
 * la URL manualmente y ejecutar lógica sensible. El sidebar oculta
 * los links según `Roles::permiso()`, pero los controladores también
 * deben defenderse.
 *
 * Uso esperado al inicio de cada método de controlador que requiera
 * un permiso específico:
 *
 * ```php
 * public function inicio(): void {
 *     requirePermiso('usuarios', 'ver');
 *     // ... resto del método
 * }
 * ```
 *
 * Comportamiento según el tipo de request:
 *   - HTML: si falla el permiso, llama a `abortar(403)` (vista 403).
 *   - API: si falla el permiso, responde JSON 403 con shape canónico
 *     `{success:false, error:"forbidden", message:"..."}` y `exit`.
 *
 * Si la sesión no tiene un rol válido (corrupto o no catalogado),
 * redirige al login — defensa en profundidad, ya que `RutaProtegida`
 * debería haber bloqueado esto antes.
 *
 * @param string $recurso Recurso del sistema (ej: 'usuarios', 'traslados').
 * @param string $accion  Acción del recurso (ej: 'ver', 'crear').
 */
function requirePermiso(string $recurso, string $accion): void
{
    $usuario = Sesion::obtener('user');
    $rol = is_array($usuario) ? (string)($usuario['rol'] ?? '') : '';

    // Defensa en profundidad: si la sesión está corrupta o el rol no
    // existe en el catálogo, mandamos al login. RutaProtegida ya
    // filtra esto, pero cubrimos el caso de un controlador que NO
    // extienda RutaProtegida (ej: ControladorDocumentos).
    if ($rol === '' || !Roles::esValido($rol)) {
        Sesion::guardar('error_login', 'Vuelve a iniciar sesion para continuar');
        redirigir('/login?error=auth');
        return;
    }

    if (Roles::permiso($rol, $recurso, $accion)) {
        return;
    }

    if (requestEsApi()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error'   => 'forbidden',
            'message' => 'No tiene permisos para acceder a este recurso.',
        ]);
        exit;
    }

    abortar(403);
}
