<?php

namespace {

    class RuntimeException extends Exception {}

    class PDOException extends RuntimeException {}

    class PDO
    {
        public const ATTR_AUTOCOMMIT = 0;
        public const ATTR_ERRMODE = 3;
        public const ATTR_CASE = 8;
        public const ATTR_CLIENT_VERSION = 5;
        public const ATTR_CONNECTION_STATUS = 7;
        public const ATTR_CURSOR = 10;
        public const ATTR_CURSOR_NAME = 11;
        public const ATTR_DEFAULT_FETCH_MODE = 19;
        public const ATTR_EMULATE_PREPARES = 20;
        public const ATTR_FETCH_CATALOG_NAMES = 17;
        public const ATTR_FETCH_TABLE_NAMES = 18;
        public const ATTR_MAX_COLUMN_LEN = 16;
        public const ATTR_ORACLE_NULLS = 4;
        public const ATTR_PERSISTENT = 12;
        public const ATTR_PREFETCH = 7;
        public const ATTR_SERVER_INFO = 6;
        public const ATTR_SERVER_VERSION = 4;
        public const ATTR_STRINGIFY_FETCHES = 21;
        public const ATTR_TIMEOUT = 2;
        public const CASE_LOWER = 2;
        public const CASE_NATURAL = 0;
        public const CASE_UPPER = 1;
        public const CURSOR_FWDONLY = 0;
        public const CURSOR_SCROLL = 1;
        public const ERR_NONE = '00000';
        public const ERRMODE_EXCEPTION = 2;
        public const ERRMODE_SILENT = 0;
        public const ERRMODE_WARNING = 1;
        public const FETCH_ASSOC = 2;
        public const FETCH_BOTH = 4;
        public const FETCH_BOUND = 4096;
        public const FETCH_CLASS = 8192;
        public const FETCH_CLASSTYPE = 262144;
        public const FETCH_COLUMN = 3;
        public const FETCH_FUNC = 10;
        public const FETCH_GROUP = 65536;
        public const FETCH_INTO = 9;
        public const FETCH_LAZY = 1;
        public const FETCH_NAMED = 11;
        public const FETCH_NUM = 3;
        public const FETCH_OBJ = 5;
        public const FETCH_ORI_ABS = 4;
        public const FETCH_ORI_FIRST = 2;
        public const FETCH_ORI_LAST = 3;
        public const FETCH_ORI_NEXT = 0;
        public const FETCH_ORI_PRIOR = 1;
        public const FETCH_PROPS_LATE = 1048576;
        public const FETCH_RAW = 2097152;
        public const FETCH_UNIQUE = 196608;
        public const NULL_EMPTY_STRING = 2;
        public const NULL_NATURAL = 0;
        public const NULL_TO_STRING = 1;

        public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null) {}
        public function beginTransaction(): bool {}
        public function commit(): bool {}
        public function errorCode(): ?string {}
        public function errorInfo(): array {}
        public function exec(string $statement): int|false {}
        public function getAttribute(int $attr): mixed {}
        public static function getAvailableDrivers(): array {}
        public function inTransaction(): bool {}
        public function lastInsertId(?string $name = null): string|false {}
        public function prepare(string $query, array $options = []): PDOStatement|false {}
        public function query(string $query, ?int $fetchMode = null, ...$fetchModeArgs): PDOStatement|false {}
        public function quote(string $string, int $type = PDO::PARAM_STR): string|false {}
        public function rollBack(): bool {}
        public function setAttribute(int $attr, mixed $value): bool {}
    }

    class PDOStatement implements Traversable
    {
        public const ATTR_CURSOR = 10;
        public const ATTR_CURSOR_NAME = 11;
        public const ATTR_FETCH_CATALOG_NAMES = 17;
        public const ATTR_FETCH_TABLE_NAMES = 18;
        public const ATTR_MAX_COLUMN_LEN = 16;
        public const ATTR_ORACLE_NULLS = 4;
        public const ATTR_PREFETCH = 7;
        public const ATTR_STRINGIFY_FETCHES = 21;
        public const FETCH_INTO = 9;

        public function bindColumn(string|int $column, mixed &$var, int $type = 0, int $maxLength = 0, mixed $driverOptions = null): bool {}
        public function bindParam(string|int $param, mixed &$var, int $type = PDO::PARAM_STR, int $maxLength = 0, mixed $driverOptions = null): bool {}
        public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool {}
        public function closeCursor(): bool {}
        public function columnCount(): int {}
        public function debugDumpParams(): ?bool {}
        public function errorCode(): ?string {}
        public function errorInfo(): array {}
        public function execute(?array $params = null): bool {}
        public function fetch(int $mode = PDO::FETCH_BOTH, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed {}
        public function fetchAll(int $mode = PDO::FETCH_BOTH, ...$args): array {}
        public function fetchColumn(int $column = 0): mixed {}
        public function fetchObject(?string $class = "stdClass", array $constructorArgs = []): object|false {}
        public function getAttribute(int $attr): mixed {}
        public function getColumnMeta(int $column): array|false {}
        public function nextRowset(): bool {}
        public function rowCount(): int {}
        public function setAttribute(int $attr, mixed $value): bool {}
        public function setFetchMode(int $mode, ...$args): bool {}
    }
}
