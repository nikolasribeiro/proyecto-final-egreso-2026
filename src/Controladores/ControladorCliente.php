<?php

namespace Controladores;

use Modelos\ModeloCliente;

class ControladorCliente
{
    public function inicio()
    {
        $nombreCliente = ModeloCliente::crear();
        vista('cliente/inicio', [
            'nombreCliente' => $nombreCliente,
        ]);
    }
}
