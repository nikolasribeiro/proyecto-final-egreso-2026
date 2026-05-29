<?php

namespace Controladores;

use Modelos\ModeloCliente;

class ControladorInicio
{
    public function inicio(): void
    {
        try {
            $nuevoCliente = ModeloCliente::crear();
            vista('inicio', [
                'nombreCliente' => $nuevoCliente,
            ], 'admin');
        } catch (\Throwable $th) {
            vista('errores/error_servidor');
        }
    }
}
