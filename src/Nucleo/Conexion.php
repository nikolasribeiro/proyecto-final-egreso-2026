<?php

namespace Nucleo;

use PDO;
use PDOException;

class Conexion {
    private static ?PDO $instancia = null;

    public static function obtenerInstancia(): PDO {
        if (self::$instancia === null) {
            // Obtenemos los valores leyendo dinámicamente el entorno de Docker / .env
            $host = $_ENV['DB_HOST'] ?? 'db';
            $db   = $_ENV['DB_NAME'] ?? $_ENV['MYSQL_DATABASE'] ?? 'database';
            $user = $_ENV['DB_USER'] ?? $_ENV['MYSQL_USER'] ?? 'elcapo';
            $pass = $_ENV['DB_PASS'] ?? $_ENV['MYSQL_PASSWORD'] ?? 'capo';
            $port = $_ENV['DB_PORT'] ?? '3306';

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