<?php

namespace Controladores;

use Nucleo\Sesion;

class ControladorDocumentos
{
    public function inicio(): void
    {
        $usuario = Sesion::usuario();

        vista('modulos/documentos/inicio', [
            'titulo_pagina' => 'Gestion de Documentos',
            'usuario' => $usuario,
            'nombre' => $usuario['nombre'] ?? '',
            'rol' => $usuario['rol'] ?? '',
        ], 'admin');
    }
}
