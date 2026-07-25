<?php

namespace Controladores;

use Nucleo\Sesion;

class ControladorTraslados
{
    public function inicio(): void
    {
        $this->renderizar('Trazabilidad de Traslados', 'modulos/traslados/inicio');
    }

    public function nuevo(): void
    {
        $this->renderizar('Nuevo Traslado', 'modulos/traslados/nuevo');
    }

    public function detalle(): void
    {
        $this->renderizar('Detalle del Traslado', 'modulos/traslados/detalle');
    }

    public function guardar(): void
    {
        // Aquí en el futuro capturaremos $_POST y usaremos el Modelo para guardar en la BD
        redirigir('/traslados');
    }

    public function actualizarEstado(): void
    {
        redirigir('/traslados/detalle');
    }

    private function renderizar(string $titulo, string $vista): void
    {
        $usuario = Sesion::usuario();

        vista($vista, [
            'titulo_pagina' => $titulo,
            'usuario' => $usuario,
            'nombre' => $usuario['nombre'] ?? '',
            'rol' => $usuario['rol'] ?? '',
        ], 'admin');
    }
}
