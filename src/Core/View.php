<?php

namespace Core;

class View
{
    public static function render(string $template, array $args = []): void
    {
        extract($args);
        $file = dirname(__DIR__) . '/Views/' . $template . '.php';

        if (file_exists($file)) {
            require $file;
        } else {
            throw new \Exception("La vista $template, ruta: $file, no existe.");
        }
    }
}
