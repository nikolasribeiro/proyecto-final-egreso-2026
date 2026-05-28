<?php

declare(strict_types=1);

namespace Nucleo;

/**
 * Clase de conexión a la Base de Datos usando PDO.
 * Implementa el patrón Singleton para garantizar una única instancia de conexión.
 */
class Bd
{
    /**
     * @var Bd|null Instancia única de la clase
     */
    private static ?Bd $instancia = null;

    /**
     * @var \PDO|null Conexión PDO activa
     */
    private ?\PDO $conexion = null;

    /**
     * Constructor privado para evitar instanciación directa.
     * Solo se accede a través de getInstancia().
     */
    private function __construct()
    {
        $this->conectar();
    }

    /**
     * Previene la clonación del objeto (patrón Singleton).
     */
    private function __clone() {}

    /**
     * Previene la deserialización del objeto.
     * @throws \Exception Siempre lanza excepción si se intenta.
     */
    public function __wakeup()
    {
        throw new \Exception("No se puede deserializar una conexión a la base de datos");
    }

    /**
     * Obtiene la instancia única de la clase Bd.
     * Si no existe, la crea; si existe, la retorna.
     * 
     * @return Bd La instancia única de la conexión
     */
    public static function getInstancia(): Bd
    {
        if (self::$instancia === null) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    /**
     * Establece la conexión con la base de datos MySQL/MariaDB.
     * Lee las credenciales desde variables de entorno.
     */
    private function conectar(): void
    {
        $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
        $nombre = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'app_db';
        $usuario = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root';
        $contrasena = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';
        $puerto = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
        $charset = $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4';

        $dsn = "mysql:host={$host};port={$puerto};dbname={$nombre};charset={$charset}";

        $opciones = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        try {
            $this->conexion = new \PDO($dsn, $usuario, $contrasena, $opciones);
        } catch (\Throwable $e) {
            error_log("Error de conexión a BD: " . $e->getMessage());
            throw new \RuntimeException("Error de conexión a la base de datos", (int)$e->getCode(), $e);
        }
    }

    /**
     * Obtiene la conexión PDO activa.
     * 
     * @return \PDO La conexión PDO
     * @throws \RuntimeException Si no hay conexión activa
     */
    public function getConexion(): \PDO
    {
        if ($this->conexion === null) {
            throw new \RuntimeException("No hay conexión activa a la base de datos");
        }
        return $this->conexion;
    }

    /**
     * Ejecuta una consulta SELECT y retorna todos los resultados.
     * USA PREPARED STATEMENTS para prevenir SQL Injection.
     * 
     * @param string $sql Consulta SQL con marcadores ?
     * @param array $parametros Parámetros para la consulta
     * @return array Resultados de la consulta
     */
    public function seleccionar(string $sql, array $parametros = []): array
    {
        try {
            $stmt = $this->getConexion()->prepare($sql);
            $stmt->execute($parametros);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            error_log("Error en SELECT: {$sql} - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Ejecuta una consulta SELECT y retorna UNA sola fila.
     * 
     * @param string $sql Consulta SQL con marcadores ?
     * @param array $parametros Parámetros para la consulta
     * @return array|null La fila encontrada o null
     */
    public function seleccionarUno(string $sql, array $parametros = []): ?array
    {
        try {
            $stmt = $this->getConexion()->prepare($sql);
            $stmt->execute($parametros);
            $resultado = $stmt->fetch();
            return $resultado ?: null;
        } catch (\PDOException $e) {
            error_log("Error en SELECT ONE: {$sql} - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Ejecuta una consulta INSERT y retorna el ID del último registro插入.
     * 
     * @param string $sql Consulta SQL INSERT
     * @param array $parametros Parámetros para la consulta
     * @return string|false El ID del registro insertado o false
     */
    public function insertar(string $sql, array $parametros = []): string|false
    {
        try {
            $stmt = $this->getConexion()->prepare($sql);
            $stmt->execute($parametros);
            return $this->getConexion()->lastInsertId();
        } catch (\PDOException $e) {
            error_log("Error en INSERT: {$sql} - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Ejecuta una consulta UPDATE y retorna el número de filas afectadas.
     * 
     * @param string $sql Consulta SQL UPDATE
     * @param array $parametros Parámetros para la consulta
     * @return int Número de filas modificadas
     */
    public function actualizar(string $sql, array $parametros = []): int
    {
        try {
            $stmt = $this->getConexion()->prepare($sql);
            $stmt->execute($parametros);
            return $stmt->rowCount();
        } catch (\PDOException $e) {
            error_log("Error en UPDATE: {$sql} - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Ejecuta una consulta DELETE y retorna el número de filas eliminadas.
     * 
     * @param string $sql Consulta SQL DELETE
     * @param array $parametros Parámetros para la consulta
     * @return int Número de filas eliminadas
     */
    public function eliminar(string $sql, array $parametros = []): int
    {
        try {
            $stmt = $this->getConexion()->prepare($sql);
            $stmt->execute($parametros);
            return $stmt->rowCount();
        } catch (\PDOException $e) {
            error_log("Error en DELETE: {$sql} - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Ejecuta una consulta personalizada (para operaciones complejas).
     * Retorna el statement para mayor control.
     * 
     * @param string $sql Consulta SQL
     * @param array $parametros Parámetros
     * @return \PDOStatement Statement preparado
     */
    public function consulta(string $sql, array $parametros = []): \PDOStatement
    {
        try {
            $stmt = $this->getConexion()->prepare($sql);
            $stmt->execute($parametros);
            return $stmt;
        } catch (\PDOException $e) {
            error_log("Error en consulta: {$sql} - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Inicia una transacción.
     */
    public function iniciarTransaccion(): void
    {
        $this->getConexion()->beginTransaction();
    }

    /**
     * Confirma la transacción actual.
     */
    public function confirmarTransaccion(): void
    {
        $this->getConexion()->commit();
    }

    /**
     * Revierte la transacción actual.
     */
    public function revertirTransaccion(): void
    {
        $this->getConexion()->rollBack();
    }
}
