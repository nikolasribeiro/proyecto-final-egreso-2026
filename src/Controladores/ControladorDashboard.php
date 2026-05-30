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
        parent::__construct();

        $usuario = Sesion::obtener('user');
        $this->nombre_usuario = $usuario['nombre'];
        $this->rol = $usuario['rol'];
    }


    /**
     * Muestra el modulo documentos
     */
    public function documentos(): void
    {
        $documentos = [
            [
                'id' => 'TRF-2024-0891',
                'nombre' => 'Protocolo de Emergencias 2024',
                'tipo' => 'PDF',
                'tamano' => '2.4 MB',
                'fecha_subida' => 'Hace 2 dias',
                'ruta' => '/uploads/protocolo_emergencia.pdf'
            ],
            [
                'id' => 'TRF-2024-0892',
                'nombre' => 'Guia de Traslado 2024',
                'tipo' => 'PDF',
                'tamano' => '1.5 MB',
                'fecha_subida' => 'Hace 3 dias',
                'ruta' => '/uploads/guia_traslado.pdf'
            ],
            [
                'id' => 'TRF-2024-0893',
                'nombre' => 'Plan de Salud 2024',
                'tipo' => 'PDF',
                'tamano' => '1.8 MB',
                'fecha_subida' => 'Hace 5 dias',
                'ruta' => '/uploads/plan_salud.pdf'
            ],
            [
                'id' => 'TRF-2024-0894',
                'nombre' => 'Tutorial Paso a paso sobre como Abortar',
                'tipo' => 'PDF',
                'tamano' => '1.8 MB',
                'fecha_subida' => 'Hace 5 dias',
                'ruta' => '/uploads/como_abortar_facil_y_sin_complicaciones.pdf'
            ]
        ];

        // Mostrar contenido del panel principal
        vista('modulos/documentos/inicio', [
            'titulo_pagina' => "Gestion de Documentos",
            'nombre' => $this->nombre_usuario,
            'rol' => $this->rol,
            'documentos' => $documentos
        ], 'admin');
    }

    /**
     * Muestra el modulo traslados
     */
    public function trasladosInicio(): void
    {
        // Mostrar contenido del panel principal
        vista('modulos/traslados/inicio', [
            'titulo_pagina' => "Trazabilidad de Traslados",
            'nombre' => $this->nombre_usuario,
            'rol' => $this->rol,
        ], 'admin');
    }

    public function nuevoTraslado(): void
    {
        // Mostrar contenido del panel principal
        vista('modulos/traslados/nuevo/inicio', [
            'titulo_pagina' => "Solicita un nuevo traslado",
            'nombre' => $this->nombre_usuario,
            'rol' => $this->rol,
        ], 'admin');
    }

    public function detalleTraslado(int $id): void
    {
        // Mostrar contenido del panel principal
        vista('modulos/traslados/detalle/inicio', [
            'titulo_pagina' => "Detalle del traslado",
            'traslado_id' => $id,
            'nombre' => $this->nombre_usuario,
            'rol' => $this->rol,
        ], 'admin');
    }
}
