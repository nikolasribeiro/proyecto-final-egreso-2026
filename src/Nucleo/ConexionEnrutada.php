<?php

namespace Nucleo;

use PDO;
use PDOException;
use PDOStatement;

/**
 * ConexionEnrutada — PDO que enruta cada sentencia al servidor correcto de
 * la topología master-slave.
 *
 * REGLA DE ENRUTAMIENTO
 * ---------------------
 *   SELECT / SHOW / DESCRIBE / EXPLAIN / WITH  ->  ESCLAVO (lectura)
 *   INSERT / UPDATE / DELETE / REPLACE / DDL   ->  MASTER (escritura)
 *   Dentro de una transacción                   ->  MASTER (todo)
 *   SELECT ... FOR UPDATE / LOCK IN SHARE MODE  ->  MASTER (lectura bloqueante)
 *
 * Es una SUBCLASE de PDO, asi que los modelos existentes (que tipan sus
 * propiedades como `PDO`) siguen funcionando sin cambios: no hay que tocar
 * ningun `prepare()` ni `query()` del codigo de negocio.
 *
 * FAIL-OPEN
 * ---------
 * Si la replica no esta disponible (caida, DNS, timeout), las lecturas caen
 * automaticamente al master y la aplicacion sigue funcionando. La app nunca
 * queda sin base de datos por un fallo de la replica.
 *
 * CONSISTENCIA "LEER LO QUE ESCRIBI"
 * -----------------------------------
 * Una replica puede ir rezagada: si se inserta una fila y en el request
 * siguiente se lee, la lectura podria ir al slave y todavia no ver la fila.
 * Por eso, tras cada COMMIT el router espera (con timeout acotado) a que la
 * replica exponga el GTID del master.
 *
 * MariaDB 10.11 NO tiene la funcion `WAIT_FOR_EXECUTED_GTID_SET` (esa es de
 * MySQL) y la imagen oficial no trae el plugin de replicacion semi-sincrona
 * (`semisync_master.so` no existe en mariadb:10.11). Por eso la espera se
 * implementa comparando el GTID del master con el del slave:
 *
 *     master @@gtid_binlog_pos -> 0-1-42
 *     slave   @@gtid_binlog_pos -> 0-1-42,0-2-9   (ya aplico el 42)
 */
class ConexionEnrutada extends PDO
{
    /** @var callable():?PDO */
    private $conectorEsclavo;

    private ?PDO $esclavo = null;

    /** Evita reintentar la conexion a la replica dentro del mismo request. */
    private bool $esclavoIntentado = false;

    private bool $replicaActiva;

    private int $esperaTimeoutMs;

    /** Mientras hay transaccion abierta, todo va al master. */
    private bool $transaccionEnCurso = false;

    public function __construct(
        string $dsn,
        ?string $usuario,
        ?string $password,
        ?array $opciones,
        callable $conectorEsclavo,
        bool $replicaActiva = true,
        int $esperaTimeoutMs = 2000
    ) {
        // La conexion padre ES la del master.
        parent::__construct($dsn, $usuario, $password, $opciones);

        $this->conectorEsclavo = $conectorEsclavo;
        $this->replicaActiva = $replicaActiva;
        $this->esperaTimeoutMs = $esperaTimeoutMs;
    }

    // -----------------------------------------------------------------
    // Clasificacion de sentencias
    // -----------------------------------------------------------------

    /**
     * Indica si la sentencia es de solo lectura (y por tanto puede ir al
     * slave).
     */
    private function esLectura(string $sql): bool
    {
        // Quito comentarios iniciales y espacios.
        $limpia = preg_replace('/\A(\s|--[^\n]*\n|\#[^\n]*\n|\/\*.*?\*\/)+/s', '', $sql);
        if (!is_string($limpia)) {
            $limpia = $sql;
        }

        if (!preg_match('/\A([A-Za-z]+)/', $limpia, $coincidencias)) {
            return false;
        }

        $keyword = strtoupper($coincidencias[1]);
        if (!in_array($keyword, ['SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN', 'WITH'], true)) {
            return false;
        }

        // Las lecturas bloqueantes necesitan el master.
        if (preg_match('/\bFOR\s+UPDATE\b/i', $limpia)
            || preg_match('/\bLOCK\s+IN\s+SHARE\s+MODE\b/i', $limpia)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Decide si esta sentencia debe ejecutarse en la replica.
     */
    private function usarEsclavo(string $sql): bool
    {
        if (!$this->replicaActiva) {
            return false;
        }
        if ($this->transaccionEnCurso) {
            return false;
        }
        return $this->esLectura($sql);
    }

    /**
     * Conexion perezosa a la replica. Devuelve null si no se puede conectar
     * (fail-open al master).
     */
    public function conexionEsclavo(): ?PDO
    {
        if ($this->esclavo !== null) {
            return $this->esclavo;
        }
        if ($this->esclavoIntentado) {
            return null;
        }
        $this->esclavoIntentado = true;

        try {
            $conexion = ($this->conectorEsclavo)();
            $this->esclavo = $conexion instanceof PDO ? $conexion : null;
        } catch (\Throwable $e) {
            error_log('[Conexion] replica no disponible: ' . $e->getMessage());
            $this->esclavo = null;
        }

        return $this->esclavo;
    }

    // -----------------------------------------------------------------
    // Metodos PDO reimplementados con enrutamiento
    // -----------------------------------------------------------------

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if ($this->usarEsclavo($query)) {
            $esclavo = $this->conexionEsclavo();
            if ($esclavo !== null) {
                try {
                    return $esclavo->prepare($query, $options);
                } catch (PDOException $e) {
                    error_log('[Conexion] la lectura fallo en la replica, se reintenta en el master: ' . $e->getMessage());
                }
            }
        }

        return parent::prepare($query, $options);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        if ($this->usarEsclavo($query)) {
            $esclavo = $this->conexionEsclavo();
            if ($esclavo !== null) {
                try {
                    if ($fetchMode === null && $fetchModeArgs === []) {
                        return $esclavo->query($query);
                    }
                    return $esclavo->query($query, $fetchMode, ...$fetchModeArgs);
                } catch (PDOException $e) {
                    error_log('[Conexion] la lectura fallo en la replica, se reintenta en el master: ' . $e->getMessage());
                }
            }
        }

        if ($fetchMode === null && $fetchModeArgs === []) {
            return parent::query($query);
        }
        return parent::query($query, $fetchMode, ...$fetchModeArgs);
    }

    public function exec(string $statement): int|false
    {
        if ($this->usarEsclavo($statement)) {
            $esclavo = $this->conexionEsclavo();
            if ($esclavo !== null) {
                try {
                    return $esclavo->exec($statement);
                } catch (PDOException $e) {
                    error_log('[Conexion] la lectura fallo en la replica, se reintenta en el master: ' . $e->getMessage());
                }
            }
        }

        return parent::exec($statement);
    }

    public function beginTransaction(): bool
    {
        // Todo lo que pase dentro de la transaccion va al master.
        $this->transaccionEnCurso = true;
        return parent::beginTransaction();
    }

    public function commit(): bool
    {
        $resultado = parent::commit();
        $this->transaccionEnCurso = false;

        if ($resultado) {
            $this->esperarReplica();
        }

        return $resultado;
    }

    public function rollBack(): bool
    {
        $resultado = parent::rollBack();
        $this->transaccionEnCurso = false;
        return $resultado;
    }

    public function inTransaction(): bool
    {
        return parent::inTransaction();
    }

    public function lastInsertId(?string $name = null): string|false
    {
        // Las escrituras van al master: el ID generado por el master.
        return parent::lastInsertId($name);
    }

    // -----------------------------------------------------------------
    // Espera de la replica (consistencia "leer lo que escribi")
    // -----------------------------------------------------------------

    /**
     * Espera a que la replica haya aplicado la escritura del master.
     * Acotada por `esperaTimeoutMs`: si la replica no llega, se loguea y se
     * sigue (la lectura siguiente podria no ver la fila).
     */
    private function esperarReplica(): void
    {
        if ($this->esperaTimeoutMs <= 0) {
            return;
        }

        $esclavo = $this->conexionEsclavo();
        if ($esclavo === null) {
            return;
        }

        try {
            $stmt = parent::query('SELECT @@gtid_binlog_pos');
            if ($stmt === false) {
                return;
            }
            $gtidMaestro = $stmt->fetchColumn();
            if (!is_string($gtidMaestro) || $gtidMaestro === '') {
                return;
            }

            $limite = microtime(true) + ($this->esperaTimeoutMs / 1000);
            do {
                $stmtEsclavo = $esclavo->query('SELECT @@gtid_binlog_pos');
                $gtidEsclavo = $stmtEsclavo === false ? false : $stmtEsclavo->fetchColumn();
                if (is_string($gtidEsclavo) && $this->gtidContiene($gtidEsclavo, $gtidMaestro)) {
                    return;
                }
                usleep(25000);
            } while (microtime(true) < $limite);

            error_log(sprintf(
                '[Conexion] la replica no aplico el GTID %s dentro de %d ms',
                $gtidMaestro,
                $this->esperaTimeoutMs
            ));
        } catch (\Throwable $e) {
            error_log('[Conexion] error esperando a la replica: ' . $e->getMessage());
        }
    }

    /**
     * True si el conjunto de GTID `$conjunto` incluye todos los de `$buscado`.
     * Formato: "0-1-42,0-2-9" (dominio-servidor-secuencia).
     */
    private function gtidContiene(string $conjunto, string $buscado): bool
    {
        $buscados = array_values(array_filter(array_map('trim', explode(',', $buscado))));
        if ($buscados === []) {
            return true;
        }

        $presentes = array_values(array_filter(array_map('trim', explode(',', $conjunto))));
        foreach ($buscados as $gtid) {
            if (!in_array($gtid, $presentes, true)) {
                return false;
            }
        }

        return true;
    }
}
