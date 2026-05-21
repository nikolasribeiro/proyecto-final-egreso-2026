<?php

namespace Controladores;

use Nucleo\Vista;
use Modelos\ModeloCliente;

class ControladorCliente
{
    public function inicio()
    {
        $nombreCliente = ModeloCliente::crear();
        Vista::mostrar('cliente/inicio', [
            'nombreCliente' => $nombreCliente,
        ]);
    }
}
