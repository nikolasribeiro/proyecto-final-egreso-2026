<?php

namespace Controladores;

use Nucleo\Sesion;
use Nucleo\RutaProtegida;

class ControladorDashboard extends RutaProtegida
{
    private string $nombre_usuario;
    private string $rol;

    public function __construct()
    {
        $usuario = Sesion::obtener('user');
        $this->nombre_usuario = $usuario['nombre'];
        $this->rol = $usuario['rol'];
    }


    /**
     * Muestra el modulo documentos
     */
    public function documentos(): void
    {
        // Mostrar contenido del panel principal
        vista('modulos/documentos/inicio', [
            'titulo_pagina' => "Gestion de Documentos",
            'nombre' => $this->nombre_usuario,
            'rol' => $this->rol,
        ], 'admin');
    }

    /**
     * Muestra el modulo documentos
     */
    public function traslados(): void
    {
        // Mostrar contenido del panel principal
        vista('modulos/traslados/inicio', [
            'titulo_pagina' => "Trazabilidad de Traslados",
            'nombre' => $this->nombre_usuario,
            'rol' => $this->rol,
        ], 'admin');
    }
}
