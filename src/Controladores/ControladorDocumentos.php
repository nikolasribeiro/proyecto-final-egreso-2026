<?php

namespace Controladores;


class ControladorDocumentos
{

    public function __construct() {}

    public function inicio(): void
    {
        redirigir('/dashboard/documentos');
    }
}
