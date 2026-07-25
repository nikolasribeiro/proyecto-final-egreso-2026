<?php

declare(strict_types=1);

namespace Nucleo;

/**
 * Fábrica de middlewares de autorización.
 *
 * Cada método retorna un `callable` que el router ejecuta antes del
 * controlador. Si el callable retorna `false`, el router aborta la
 * request (redirect a /login, 403, etc.).
 */
final class Middleware
{
    /**
     * Solo permite el acceso a invitados (no autenticados).
     * Si ya está autenticado, redirige a /.
     */
    public static function invitado(): callable
    {
        return static function (): bool {
            if (Sesion::autenticada()) {
                redirigir('/');
                return false;
            }
            return true;
        };
    }

    /**
     * Requiere usuario autenticado. Si no, redirige a /login.
     */
    public static function auth(): callable
    {
        return static function (): bool {
            if (!Sesion::autenticada()) {
                redirigir('/login');
                return false;
            }
            return true;
        };
    }

    /**
     * Requiere usuario autenticado y que su rol esté en la lista.
     * Si no está autenticado, redirige a /login. Si no tiene el rol,
     * responde 403.
     */
    public static function rol(string ...$roles): callable
    {
        return static function () use ($roles): bool {
            if (!Sesion::autenticada()) {
                redirigir('/login');
                return false;
            }
            if (!Sesion::tieneRol(...$roles)) {
                abortar(403);
                return false;
            }
            return true;
        };
    }
}
