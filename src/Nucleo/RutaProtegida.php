<?php

namespace Nucleo;

class RutaProtegida
{
    /**
     * Este constructor se ejecuta automáticamente cada vez que el
     * enrutador instancia un controlador que hereda de esta clase.
     * * @param array $rolesPermitidos Lista de roles que pueden acceder. Si está vacío, solo requiere login.
     */
    public function __construct(array $rolesPermitidos = [])
    {
        // 1. Verificamos si existe el usuario en sesión
        // Recuerda que en ControladorAuth guardas un array 'user' con 'rol'
        $usuario = Sesion::obtener('user');

        $esApi = self::requestEsApi();

        if (!$usuario) {
            if ($esApi) {
                // Endpoint API: respondemos JSON 401 en lugar de redirigir al login.
                self::responderJson(401, 'unauthenticated', 'Debe iniciar sesión para acceder a este recurso.');
                return;
            }
            // Web: redirigimos al login como siempre.
            Sesion::guardar('error_login', 'Vuelve a iniciar sesion para continuar');
            redirigir('/login?error=auth');
        }

        // 2. Verificamos la autorización de roles (si se especificaron)
        if (!empty($rolesPermitidos)) {
            $rolUsuario = $usuario['rol'] ?? 'invitado';

            // Si el rol del usuario no está dentro del array de permitidos, bloqueamos
            if (!in_array($rolUsuario, $rolesPermitidos, true)) {
                if ($esApi) {
                    // API: JSON 403 explícito (no ocultamos el endpoint con 404
                    // porque los consumidores JS necesitan distinguir auth de authz).
                    self::responderJson(403, 'forbidden', 'No tiene permisos para acceder a este recurso.');
                    return;
                }
                // Web: HTML 403 vía helper de aborto.
                abortar(403);
            }
        }
    }

    /**
     * Devuelve true si la request actual apunta a un endpoint de API
     * (prefijo /api/). Usado para alternar entre respuesta HTML y JSON
     * en errores de autenticación / autorización.
     */
    private static function requestEsApi(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($uri, PHP_URL_PATH) ?? '';
        return str_starts_with($path, '/api/');
    }

    /**
     * Helper para emitir una respuesta JSON de error y cortar la ejecución.
     */
    private static function responderJson(int $codigo, string $error, string $mensaje): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error'   => $error,
            'message' => $mensaje,
        ]);
        exit;
    }
}
