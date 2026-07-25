<?php

declare(strict_types=1);

namespace Nucleo;

class Enrutador
{
    /**
     * @var array Almacena todas las rutas registradas.
     */
    private array $rutas = [];

    /**
     * Registra una ruta para el método GET.
     */
    public function get(string $uri, array $accion): Ruta
    {
        return $this->agregarRuta('GET', $uri, $accion);
    }

    /**
     * Registra una ruta para el método POST.
     */
    public function post(string $uri, array $accion): Ruta
    {
        return $this->agregarRuta('POST', $uri, $accion);
    }

    /**
     * Registra una ruta para el método PUT.
     */
    public function put(string $uri, array $accion): Ruta
    {
        return $this->agregarRuta('PUT', $uri, $accion);
    }

    /**
     * Registra una ruta para el método PATCH.
     */
    public function patch(string $uri, array $accion): Ruta
    {
        return $this->agregarRuta('PATCH', $uri, $accion);
    }

    /**
     * Registra una ruta para el método DELETE.
     */
    public function delete(string $uri, array $accion): Ruta
    {
        return $this->agregarRuta('DELETE', $uri, $accion);
    }

    /**
     * Método interno para añadir una ruta con su wrapper fluido.
     */
    private function agregarRuta(string $metodo, string $uri, array $accion): Ruta
    {
        // Convertimos parámetros estilo {id} en expresiones regulares.
        $patron = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<\1>[a-zA-Z0-9_-]+)', $uri);
        $patron = "#^" . $patron . "/?$#";

        $registro = [
            'metodo' => $metodo,
            'patron' => $patron,
            'controlador' => $accion[0],
            'nombre_metodo' => $accion[1],
            'middlewares' => [],
        ];

        // Insertamos por referencia para que Ruta::middleware() pueda
        // mutar la entrada del array directamente.
        $this->rutas[] = &$registro;
        unset($registro);

        // Devolvemos un wrapper ligado al router y al índice del array.
        $indice = array_key_last($this->rutas);
        return new Ruta($this, $indice);
    }

    /**
     * Acceso interno para que Ruta::middleware() modifique el array.
     */
    public function agregarMiddleware(int $indice, callable $middleware): void
    {
        $this->rutas[$indice]['middlewares'][] = $middleware;
    }

    /**
     * Despacha la solicitud HTTP actual.
     */
    public function despachar(string $metodo, string $uri): void
    {
        $uri = parse_url($uri, PHP_URL_PATH);

        foreach ($this->rutas as $ruta) {
            if ($ruta['metodo'] !== $metodo) {
                continue;
            }
            if (!preg_match($ruta['patron'], $uri, $coincidencias)) {
                continue;
            }

            $parametros = array_filter($coincidencias, 'is_string', ARRAY_FILTER_USE_KEY);

            // Ejecutar middlewares en orden. Si alguno retorna false,
            // el router ya habrá abortado la request (redirect/403).
            foreach ($ruta['middlewares'] ?? [] as $middleware) {
                $resultado = $middleware();
                if ($resultado === false) {
                    return;
                }
            }

            $claseControlador = $ruta['controlador'];
            $nombreMetodo = $ruta['nombre_metodo'];

            if (class_exists($claseControlador)) {
                $instanciaControlador = new $claseControlador();
                if (method_exists($instanciaControlador, $nombreMetodo)) {
                    call_user_func_array([$instanciaControlador, $nombreMetodo], $parametros);
                    return;
                }
            }
        }

        abortar(404);
    }
}
