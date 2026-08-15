<?php

namespace Nucleo;

use PDO;
use PDOException;

class Conexion
{
    private static ?PDO $instancia = null;

    public static function obtenerInstancia(): PDO
    {
        if (self::$instancia === null) {
            // Usar getenv() en lugar de $_ENV para asegurar la lectura en Docker
            $host = getenv('DB_HOST') ?: 'db';

          // voy a probar con ésto # 116
            $db   = getenv('DB_NAME') ?: getenv('MYSQL_DATABASE') ?: 'nico';
            $user = getenv('DB_USER') ?: getenv('MYSQL_USER') ?: 'nico';
            $pass = getenv('DB_PASS') ?: getenv('MYSQL_PASSWORD') ?: 'nico';
            
            $port = getenv('DB_PORT') ?: '3306';

            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

            try {
                self::$instancia = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                throw new \Exception("Error al conectar con la base de datos: " . $e->getMessage());
            }
        }

        return self::$instancia;
    }
}
