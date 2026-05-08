<?php
ini_set('display_errors', '0');

// Evita Clickjacking
header("X-Frame-Options: DENY");

// Evita MIME-Sniffing
header("X-Content-Type-Options: nosniff");

// Evita XSS
header("X-XSS-Protection: 1; mode=block");

// CSP (Content Security Policy)
header("Content-Security-Policy: default-src 'self';");

spl_autoload_register(function ($class_name) {
    $file = __DIR__ . '/' . str_replace('\\', '/', $class_name) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

\Core\Session::start();

set_exception_handler(function (\Throwable $e) {
    error_log($e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());

    http_response_code(500);
    try {
        $controller = new \Controllers\ErrorsController();
        $controller->serverError();
    } catch (\Throwable $e2) {
        error_log("FALLO EN CASCADA (Error View): " . $e2->getMessage());
    }
    exit;
});

// ==========================================
// ROUTER CENTRAL
// ==========================================
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

$route = $method . ' ' . $uri;

switch ($route) {
    // Pagina Principal
    case 'GET /':
        $controller = new \Controllers\IndexController();
        $controller->index();
        break;


    // --- FALLBACK (404) ---
    default:
        http_response_code(404);
        $controller = new \Controllers\ErrorsController();
        $controller->notFound();
        break;
}
