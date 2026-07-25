<?php

declare(strict_types=1);

namespace Nucleo;

/**
 * Wrapper fluido para asociar middlewares a una ruta recién registrada.
 *
 * Se devuelve automáticamente desde Enrutador::get/post/... y permite
 * encadenar `->middleware(...)` antes de pasar a la siguiente ruta.
 */
final class Ruta
{
    public function __construct(
        private Enrutador $enrutador,
        private int $indice
    ) {
    }

    /**
     * Encadena uno o más middlewares. Cada middleware debe ser
     * `callable(): bool`. Si retorna `false`, el router aborta la
     * request (redirect a /login, 403, etc.).
     */
    public function middleware(callable ...$middlewares): self
    {
        foreach ($middlewares as $middleware) {
            $this->enrutador->agregarMiddleware($this->indice, $middleware);
        }
        return $this;
    }
}
