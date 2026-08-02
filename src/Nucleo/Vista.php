<?php

declare(strict_types=1);

namespace Nucleo;

class Vista
{
    /**
     * Renderiza una vista dentro de un layout especificado.
     * 
     * @param string $plantilla La ruta de la vista relativa a la carpeta Vistas (ej: 'inicio' o 'cliente/inicio')
     * @param array $datos Datos extraídos como variables disponibles en la vista
     * @param string $plantillaBase El nombre del layout base (ej: 'admin')
     */
    public static function mostrar(string $plantilla, array $datos = [], ?string $plantillaBase = null): void
    {
        extract($datos);

        $archivoVista = dirname(__DIR__) . '/Vistas/' . $plantilla . '.php';

        if (file_exists($archivoVista)) {
            // Activamos el "Output Buffering".
            // Todo el HTML (o echos) que esté en la vista no se imprimirá en pantalla,
            // sino que se guardará temporalmente en la memoria de PHP.
            ob_start();

            // Requerimos el archivo de la vista. Su salida HTML queda atrapada en el buffer.
            require $archivoVista;

            // Recogemos todo lo atrapado y lo guardamos en la variable $contenido, limpiando el buffer.
            $contenido = ob_get_clean();

            // Ahora verificamos si el layout existe y lo requerimos.
            // Si el $plantillaBase pasado es nulo o vacío (ej. para respuestas JSON o fragmentos parciales),
            // directamente imprimimos $contenido.
            if ($plantillaBase) {
                $archivoPlantilla = dirname(__DIR__) . '/Vistas/plantillas/' . $plantillaBase . '.php';
                if (file_exists($archivoPlantilla)) {
                    // El layout ahora tendrá acceso a la variable $contenido inyectada arriba.
                    require $archivoPlantilla;
                } else {
                    throw new \Exception("La plantilla base $plantillaBase, ruta: $archivoPlantilla, no existe.");
                }
            } else {
                echo $contenido;
            }
        } else {
            throw new \Exception("La vista $plantilla, ruta: $archivoVista, no existe.");
        }
    }
}
