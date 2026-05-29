<?php

declare(strict_types=1);

use Nucleo\Vista;

/**
 * Escapa strings (texto) para prevenir ataques XSS (Cross-Site Scripting).
 * Obligatorio usarlo en las vistas para cualquier variable dinámica.
 * 
 * @param string|null $string El texto a escapar
 * @return string El texto seguro y escapado
 */
function e(?string $string): string
{
    if ($string === null) {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Atajo simple para mostrar una vista sin necesidad de importar la clase Vista
 * en cada Controlador.
 * 
 * @param string $plantilla Ruta de la vista
 * @param array $datos Variables para la vista
 * @param string $plantillaBase El layout a utilizar
 */
function vista(string $plantilla, array $datos = [], string $plantillaBase = 'app'): void
{
    Vista::mostrar($plantilla, $datos, $plantillaBase);
}

/**
 * Detiene la ejecución y envía una cabecera HTTP de redirección al navegador.
 *
 * @param string $url URL destino
 */
function redirigir(string $url): void
{
    header("Location: $url");
    exit;
}

/**
 * Detiene la ejecución, envía un código de error HTTP y muestra la vista de error.
 * 
 * @param int $codigo Código HTTP (ej: 404, 500)
 */
function abortar(int $codigo = 404): void
{
    http_response_code($codigo);

    try {
        if ($codigo === 404) {
            $controlador = new \Controladores\ControladorErrores();
            $controlador->noEncontrado();
        } elseif ($codigo === 500) {
            $controlador = new \Controladores\ControladorErrores();
            $controlador->errorServidor();
        } else {
            echo "Error $codigo";
        }
    } catch (\Throwable $e) {
        // En caso de que falle incluso la vista de error
        echo "Error Crítico: " . $codigo;
    }

    exit;
}

/**
 * Renderiza un componente visual de forma aislada.
 * * @param string $ruta Ruta relativa a la carpeta Componentes (ej: 'sidebar/sidebar')
 * @param array $datos Variables inyectadas al componente
 */
function componente(string $ruta, array $datos = []): void
{
    // Extrae las variables para que vivan solo dentro de este componente
    extract($datos);

    // Resuelve la ruta absoluta partiendo desde el núcleo hacia la carpeta de Vistas
    $archivo = dirname(__DIR__) . '/Componentes/' . $ruta . '.php';

    if (file_exists($archivo)) {
        require $archivo;
    } else {
        // Manejo amigable de errores en la interfaz para no romper todo el layout
        echo "";
    }
}
