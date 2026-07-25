<?php

namespace Nucleo;

class Sesion
{
    /** Llave canónica para la identidad del usuario autenticado. */
    private const CLAVE_USUARIO = 'usuario';

    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');

        $esProduccion = getenv('APP_ENVIRONMENT') === 'production';

        // Cookie host-only: no fijar `domain` para que el navegador
        // limite la cookie al host exacto (sin subdominios).
        // SameSite=Lax para que navegadores con SameSite policies
        // estrictas permítan el subrequest de nginx (auth_request)
        // cuando llega desde un enlace externo.
        session_set_cookie_params([
            'lifetime' => 3600 * 24, // 24 horas
            'path' => '/',
            'secure' => $esProduccion,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();

        self::comprobarRegeneracion();
    }

    private static function comprobarRegeneracion(): void
    {
        $intervalo = 60 * 15;

        if (isset($_SESSION['ultima_regeneracion'])) {
            if (time() - $_SESSION['ultima_regeneracion'] >= $intervalo) {
                session_regenerate_id(true);
                $_SESSION['ultima_regeneracion'] = time();
            }
        } else {
            $_SESSION['ultima_regeneracion'] = time();
        }
    }

    public static function guardar(string $clave, mixed $valor): void
    {
        $_SESSION[$clave] = $valor;
    }

    public static function obtener(string $clave, mixed $porDefecto = null): mixed
    {
        return $_SESSION[$clave] ?? $porDefecto;
    }

    public static function eliminar(string $clave): void
    {
        unset($_SESSION[$clave]);
    }

    public static function destruir(): void
    {
        // Vaciar $_SESSION antes de destruir para que callbacks y
        // listeners no vean datos obsoletos.
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }

        // Expira la cookie replicando los mismos parámetros que se
        // usaron al crearla, sin `domain` (host-only).
        $esProduccion = getenv('APP_ENVIRONMENT') === 'production';
        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => $esProduccion,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    public static function generarTokenCsrf(): string
    {
        if (empty($_SESSION['token_csrf'])) {
            $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['token_csrf'];
    }

    /**
     * Regenera el token CSRF (recomendado tras login exitoso).
     */
    public static function regenerarTokenCsrf(): string
    {
        $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['token_csrf'];
    }

    public static function validarTokenCsrf(string $token): bool
    {
        if (empty($_SESSION['token_csrf']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['token_csrf'], $token);
    }

    // ==========================================
    // Identidad de usuario
    // ==========================================

    /**
     * Marca al usuario como autenticado y guarda su identidad mínima
     * en sesión. Regenera el ID de sesión inmediatamente para
     * prevenir session fixation.
     *
     * @param array{id:int,email:string,nombre:string,rol:string} $usuario
     */
    public static function autenticar(array $usuario): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION[self::CLAVE_USUARIO] = [
            'id' => (int) $usuario['id'],
            'email' => (string) $usuario['email'],
            'nombre' => (string) $usuario['nombre'],
            'rol' => (string) $usuario['rol'],
        ];
        $_SESSION['ultima_regeneracion'] = time();
    }

    public static function autenticada(): bool
    {
        return isset($_SESSION[self::CLAVE_USUARIO]['id']);
    }

    /**
     * @return array{id:int,email:string,nombre:string,rol:string}|null
     */
    public static function usuario(): ?array
    {
        $datos = $_SESSION[self::CLAVE_USUARIO] ?? null;
        if (!is_array($datos) || !isset($datos['id'])) {
            return null;
        }
        return $datos;
    }

    public static function idUsuario(): ?int
    {
        $usuario = self::usuario();
        return $usuario['id'] ?? null;
    }

    /**
     * Chequea si el usuario autenticado tiene uno de los roles dados.
     */
    public static function tieneRol(string ...$roles): bool
    {
        $usuario = self::usuario();
        if ($usuario === null) {
            return false;
        }
        return in_array($usuario['rol'], $roles, true);
    }
}
