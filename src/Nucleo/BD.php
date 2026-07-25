<?php

declare(strict_types=1);

namespace Nucleo;

use PDO;
use PDOException;

/**
 * Acceso centralizado a la base de datos MariaDB de la aplicación.
 *
 * Usa las variables de entorno MYSQL_* (completadas por docker-compose
 * desde .env). Construye un único PDO por request.
 *
 * Lee DB_HOST y DB_PORT de `getenv()` con defaults a `db` y `3306`
 * para mantener compatibilidad con la configuración existente.
 */
final class BD
{
    private static ?PDO $conexion = null;

    public static function conexion(): PDO
    {
        if (self::$conexion instanceof PDO) {
            return self::$conexion;
        }

        $host = getenv('DB_HOST') ?: 'db';
        $port = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('MYSQL_DATABASE') ?: '';
        $user = getenv('MYSQL_USER') ?: '';
        $pass = getenv('MYSQL_PASSWORD') ?: '';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

        try {
            self::$conexion = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            // No exponer credenciales ni DSN en logs visibles.
            error_log('BD conexion: ' . $e->getMessage());
            throw $e;
        }

        return self::$conexion;
    }
}
