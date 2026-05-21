<?php

namespace Controladores;


class ControladorErrores
{
    public function noEncontrado(): void
    {
        vista('errores/no_encontrado', [
            "titulo" => "Pagina no encontrada",
        ]);
    }

    public function errorServidor(): void
    {
        vista('errores/error_servidor');
    }
}
