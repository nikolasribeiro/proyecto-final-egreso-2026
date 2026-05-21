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
    public function get(string $uri, array $accion): void
    {
        $this->agregarRuta('GET', $uri, $accion);
    }

    /**
     * Registra una ruta para el método POST.
     */
    public function post(string $uri, array $accion): void
    {
        $this->agregarRuta('POST', $uri, $accion);
    }

    /**
     * Registra una ruta para el método PUT.
     */
    public function put(string $uri, array $accion): void
    {
        $this->agregarRuta('PUT', $uri, $accion);
    }

    /**
     * Registra una ruta para el método PATCH.
     */
    public function patch(string $uri, array $accion): void
    {
        $this->agregarRuta('PATCH', $uri, $accion);
    }

    /**
     * Registra una ruta para el método DELETE.
     */
    public function delete(string $uri, array $accion): void
    {
        $this->agregarRuta('DELETE', $uri, $accion);
    }

    /**
     * Método interno para añadir rutas al array de configuración.
     */
    private function agregarRuta(string $metodo, string $uri, array $accion): void
    {
        // Convertimos parámetros estilo {id} en expresiones regulares seguras
        $patron = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<\1>[a-zA-Z0-9_-]+)', $uri);
        $patron = "#^" . $patron . "/?$#";

        $this->rutas[] = [
            'metodo' => $metodo,
            'patron' => $patron,
            'controlador' => $accion[0], // Ej: ControladorInicio::class
            'nombre_metodo' => $accion[1] // Ej: 'inicio'
        ];
    }

    /**
     * Despacha la solicitud HTTP actual.
     */
    public function despachar(string $metodo, string $uri): void
    {
        // Limpiamos la URL de query params para el match
        $uri = parse_url($uri, PHP_URL_PATH);

        foreach ($this->rutas as $ruta) {
            if ($ruta['metodo'] === $metodo && preg_match($ruta['patron'], $uri, $coincidencias)) {

                // Extraemos los parámetros de la URL usando los nombres de grupo de la regex
                $parametros = array_filter($coincidencias, 'is_string', ARRAY_FILTER_USE_KEY);

                $claseControlador = $ruta['controlador'];
                $nombreMetodo = $ruta['nombre_metodo'];

                if (class_exists($claseControlador)) {
                    $instanciaControlador = new $claseControlador();
                    if (method_exists($instanciaControlador, $nombreMetodo)) {
                        // Llamamos al método pasándole los parámetros encontrados en la URL
                        call_user_func_array([$instanciaControlador, $nombreMetodo], $parametros);
                        return;
                    }
                }
            }
        }

        // Si llegamos aquí, no hubo coincidencia (Ruta no encontrada)
        abortar(404);
    }
}
