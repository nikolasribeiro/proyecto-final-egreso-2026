<?php

namespace Nucleo;

use PDO;
use PDOException;

/**
 * Conexion — fabrica de la conexion PDO de la aplicacion.
 *
 * Implementa la estrategia MASTER-SLAVE:
 *   - El MASTER (DB_HOST) recibe todas las escrituras.
 *   - El SLAVE (DB_SLAVE_HOST) recibe las lecturas.
 *
 * El enrutamiento lo hace `ConexionEnrutada` (una subclase de PDO), asi que
 * el codigo de negocio no cambia: los modelos siguen llamando a
 * `prepare()`, `query()` y `exec()` como siempre.
 *
 * Configuracion por variables de entorno (las inyecta docker-compose.yml):
 *   DB_HOST                      maestro (default: db-master)
 *   DB_SLAVE_HOST                esclavo (default: db-slave)
 *   DB_NAME / DB_USER / DB_PASS  credenciales
 *   DB_READ_REPLICA_ENABLED      activa el split read/write (default: true)
 *   DB_REPLICA_WAIT_AFTER_WRITE  espera a la replica tras cada escritura
 *   DB_REPLICA_WAIT_TIMEOUT_MS   timeout de esa espera (default: 2000)
 */
class Conexion
{
    private static ?PDO $instancia = null;

    private static ?PDO $instanciaLectura = null;

    /**
     * Conexion principal (master) con enrutamiento de lecturas al slave.
     */
    public static function obtenerInstancia(): PDO
    {
        if (self::$instancia === null) {
            // Usar getenv() en lugar de $_ENV para asegurar la lectura en Docker
            $host = getenv('DB_HOST') ?: 'db-master';
            $db   = getenv('DB_NAME') ?: getenv('MYSQL_DATABASE') ?: 'clinicas_db';
            $user = getenv('DB_USER') ?: getenv('MYSQL_USER') ?: 'songbird_app';
            $pass = getenv('DB_PASS') ?: getenv('MYSQL_PASSWORD') ?: '';

            $port = getenv('DB_PORT') ?: '3306';

            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

            $opciones = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $replicaActiva = self::replicaHabilitada();
            $esperaMs = self::esperaReplicaHabilitada()
                ? (int) (getenv('DB_REPLICA_WAIT_TIMEOUT_MS') ?: 2000)
                : 0;

            // Conexion perezosa a la replica: se resuelve en el primer uso.
            $conectorEsclavo = static function () use ($db, $user, $pass, $port, $opciones): ?PDO {
                $hostEsclavo = getenv('DB_SLAVE_HOST') ?: 'db-slave';
                $dsnEsclavo = "mysql:host={$hostEsclavo};port={$port};dbname={$db};charset=utf8mb4";
                try {
                    return new PDO($dsnEsclavo, $user, $pass, $opciones);
                } catch (PDOException $e) {
                    // Fail-open: la app sigue operando contra el master.
                    error_log('[Conexion] no se pudo conectar a la replica (' . $hostEsclavo . '): ' . $e->getMessage());
                    return null;
                }
            };

            try {
                self::$instancia = new ConexionEnrutada(
                    $dsn,
                    $user,
                    $pass,
                    $opciones,
                    $conectorEsclavo,
                    $replicaActiva,
                    $esperaMs
                );
            } catch (PDOException $e) {
                throw new \Exception("Error al conectar con la base de datos: " . $e->getMessage());
            }
        }

        return self::$instancia;
    }

    /**
     * Conexion explicita de SOLO LECTURA contra la replica.
     *
     * Para consultas de reportes o listados largos donde esta bien leer datos
     * potencialmente rezagados. Si la replica no esta disponible, devuelve la
     * conexion principal (fail-open).
     */
    public static function obtenerInstanciaLectura(): PDO
    {
        $instancia = self::obtenerInstancia();

        if ($instancia instanceof ConexionEnrutada) {
            $esclavo = $instancia->conexionEsclavo();
            if ($esclavo !== null) {
                self::$instanciaLectura = $esclavo;
                return $esclavo;
            }
        }

        return $instancia;
    }

    /**
     * El split read/write esta activo? (DB_READ_REPLICA_ENABLED)
     */
    private static function replicaHabilitada(): bool
    {
        $valor = getenv('DB_READ_REPLICA_ENABLED');
        if ($valor === false || $valor === '') {
            return true;
        }
        return in_array(strtolower((string) $valor), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Hay que esperar a la replica despues de cada escritura?
     * (DB_REPLICA_WAIT_AFTER_WRITE)
     */
    private static function esperaReplicaHabilitada(): bool
    {
        $valor = getenv('DB_REPLICA_WAIT_AFTER_WRITE');
        if ($valor === false || $valor === '') {
            return true;
        }
        return in_array(strtolower((string) $valor), ['1', 'true', 'yes', 'on'], true);
    }
}
