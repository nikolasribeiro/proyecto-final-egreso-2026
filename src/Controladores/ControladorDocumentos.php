<?php

namespace Controladores;

class ControladorDocumentos
{
    public function inicio(): void
    {
        vista("modulos/documentos/inicio", ["titulo_pagina" => "Gestion de Documentos"], "admin");
    }
}
