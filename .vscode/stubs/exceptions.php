<?php

namespace {
    /**
     * @property-read string $message
     * @property-read int $code
     * @property-read string $file
     * @property-read int $line
     */
    class Exception {
        public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null) {}
        public function getMessage(): string {}
        public function getCode(): int {}
        public function getFile(): string {}
        public function getLine(): int {}
        public function getTrace(): array {}
        public function getPrevious(): ?\Throwable {}
        public function getTraceAsString(): string {}
        public function __toString(): string {}
    }

    class Error extends Exception {}

    class RuntimeException extends Exception {}

    class PDOException extends RuntimeException {
        public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null) {}
    }

    class Throwable {
        public function getMessage(): string {}
        public function getCode(): int {}
        public function getFile(): string {}
        public function getLine(): int {}
        public function getTrace(): array {}
        public function getPrevious(): ?\Throwable {}
        public function getTraceAsString(): string {}
        public function __toString(): string {}
    }
}
