<?php

namespace Controladores;

use Modelos\ModeloCliente;

class ControladorInicio
{
    public function documentos(): void
    {
        vista("inicio", ["titulo_pagina" => "tituloprueba"], "admin");
    }
}
