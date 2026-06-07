<?php

namespace Controladores;

class ControladorTraslados
{
    public function inicio(): void
    {
        vista('traslados/inicio', [
            'titulo_pagina' => 'Trazabilidad de Traslados',
            'nombre' => 'Administrador', // Variables requeridas por el sidebar
            'rol' => 'Admin'
        ], 'admin');
    }

    public function nuevo(): void
    {
        vista('traslados/nuevo', [
            'titulo_pagina' => 'Nuevo Traslado',
            'nombre' => 'Administrador',
            'rol' => 'Admin'
        ], 'admin');
    }

    public function detalle(): void
    {
        vista('traslados/detalle', [
            'titulo_pagina' => 'Detalle del Traslado',
            'nombre' => 'Administrador',
            'rol' => 'Admin'
        ], 'admin');
    }

    // ... tus métodos inicio(), nuevo() y detalle() ...

    public function guardar(): void
    {
        // Aquí en el futuro capturaremos $_POST y usararemos el Modelo para guardar en la BD
        
        // Simulamos éxito y volvemos al listado
        redirigir('/traslados');
    }

    public function actualizarEstado(): void
    {
        // Aquí en el futuro capturaremos el ID del traslado y actualizaremos su estado en la BD
        
        // Simulamos éxito y volvemos a cargar la vista de detalle
        redirigir('/traslados/detalle');
    }
}

    
