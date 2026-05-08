<?php

namespace Core;

class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_only_cookies', 1);
        ini_set('session.use_strict_mode', 1);

        $isProduction = getenv('APP_ENVIRONMENT') === 'production';

        session_set_cookie_params([
            'lifetime' => 3600 * 24, // 24 horas
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => $isProduction,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        session_start();

        self::checkRegeneration();
    }

    private static function checkRegeneration(): void
    {
        $interval = 60 * 15;

        if (isset($_SESSION['last_regeneration'])) {
            if (time() - $_SESSION['last_regeneration'] >= $interval) {
                session_regenerate_id(true);
                $_SESSION['last_regeneration'] = time();
            }
        } else {
            $_SESSION['last_regeneration'] = time();
        }
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        session_unset();
        session_destroy();

        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 3600,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    public static function generateCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCsrfToken(string $token): bool
    {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
