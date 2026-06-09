<?php

namespace Controladores;

class ControladorDocumentos
{
    public function inicio(): void
    {
        vista("documentos/inicio", ["titulo_pagina" => "tituloprueba"], "admin");
    }
}
