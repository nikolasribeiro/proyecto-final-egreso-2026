<?php

declare(strict_types=1);

namespace Modelos;

use Nucleo\BD;
use PDO;

/**
 * Acceso a datos de la tabla `usuarios`.
 *
 * Todas las consultas usan sentencias preparadas. Nunca se devuelve
 * `password_hash` a una vista — el hash solo se maneja en
 * `verificarPassword()` para reducir superficie de exposición.
 */
final class ModeloUsuario
{
    /**
     * Busca un usuario por email (case-insensitive).
     * Retorna el registro completo (incluyendo password_hash) o null.
     */
    public static function buscarPorEmail(string $email): ?array
    {
        $emailNormalizado = strtolower(trim($email));

        if ($emailNormalizado === '') {
            return null;
        }

        $stmt = BD::conexion()->prepare(
            'SELECT id, email, password_hash, nombre, rol, creado_en, actualizado_en
               FROM usuarios
              WHERE email = :email
              LIMIT 1'
        );
        $stmt->execute(['email' => $emailNormalizado]);
        $fila = $stmt->fetch();

        return $fila !== false ? $fila : null;
    }

    /**
     * Busca un usuario por ID.
     */
    public static function buscarPorId(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = BD::conexion()->prepare(
            'SELECT id, email, password_hash, nombre, rol, creado_en, actualizado_en
               FROM usuarios
              WHERE id = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch();

        return $fila !== false ? $fila : null;
    }

    /**
     * Verifica una contraseña contra un hash bcrypt.
     * Usa `password_verify()` nativo de PHP.
     */
    public static function verificarPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Hash bcrypt compatible con `password_verify()`.
     * Útil para crear usuarios programáticamente.
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }
}
