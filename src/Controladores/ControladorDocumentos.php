<?php

namespace Controladores;

use Nucleo\Sesion;

class ControladorDocumentos
{
    private string $rutaDashboard = '/dashboard/documentos';
    private string $rutaLogin = '/login';

    public function __construct() {}

    public function inicio(): void
    {

        if (Sesion::obtener('user')) {
            redirigir($this->rutaDashboard);
            return;
        }

        redirigir($this->rutaLogin);
        return;
    }
}
