<?php

namespace Nucleo;

class Sesion
{
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');

        $esProduccion = getenv('APP_ENVIRONMENT') === 'production';

        session_set_cookie_params([
            'lifetime' => 3600 * 24, // 24 horas
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => $esProduccion,
            'httponly' => true,
            'samesite' => 'Strict',
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
        session_unset();
        session_destroy();

        $parametros = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 3600,
            $parametros['path'],
            $parametros['domain'],
            $parametros['secure'],
            $parametros['httponly']
        );
    }

    public static function generarTokenCsrf(): string
    {
        if (empty($_SESSION['token_csrf'])) {
            $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['token_csrf'];
    }

    public static function validarTokenCsrf(string $token): bool
    {
        if (empty($_SESSION['token_csrf']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['token_csrf'], $token);
    }
}
