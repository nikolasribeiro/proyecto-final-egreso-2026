<?php

namespace Controladores;

use Modelos\ModeloTraslado; // 1. Importamos el modelo de base de datos

class ControladorTraslados
{
    private ModeloTraslado $modelo;

    // 2. Creamos el constructor para inicializar la conexión
    public function __construct()
    {
        $this->modelo = new ModeloTraslado();
    }

    public function inicio(): void
    {
        // Obtenemos los datos dinámicos desde MariaDB
        $traslados = $this->modelo->obtenerTodosActivos();

        vista('modulos/traslados/inicio', [
            'titulo_pagina' => 'Trazabilidad de Traslados',
            'nombre'        => 'Administrador', // Mantenemos tus variables requeridas
            'rol'           => 'Admin',
            'traslados'     => $traslados       // <-- Inyectamos la información de la BD
        ], 'admin');
    }

    public function nuevo(): void
    {
        $choferes = $this->modelo->obtenerChoferesDisponibles();
        $enfermeros = $this->modelo->obtenerEnfermeros();
        $vehiculos  = $this->modelo->obtenerVehiculosDisponibles();
        $ubicaciones= $this->modelo->obtenerUbicaciones();
        
        vista('modulos/traslados/nuevo', [
            'titulo_pagina' => 'Nuevo Traslado',
            'nombre'        => 'Administrador',
            'rol'           => 'Admin',
            'choferes'      => $choferes,
            'enfermeros'    => $enfermeros,
            'vehiculos'     => $vehiculos,
            'ubicaciones'   => $ubicaciones
        ], 'admin');
    }

    // 3. Modificamos detalle para recibir parámetros de la URL (ej: /traslados/detalle/5)
    public function detalle(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $traslado = $id > 0 ? $this->modelo->obtenerPorId($id) : null;

        vista('modulos/traslados/detalle', [
            'titulo_pagina' => 'Detalle del Traslado',
            'nombre'        => 'Administrador',
            'rol'           => 'Admin',
            'traslado'      => $traslado        // <-- Inyectamos el traslado específico
        ], 'admin');
    }

    public function guardar(): void
    {
        // En el futuro, aquí harás algo como:
        // $this->modelo->crearSolicitud($_POST);

        // Mantenemos tu redirección intacta
        redirigir('/traslados');
    }

    public function actualizarEstado(): void
    {
        // Aquí en el futuro capturaremos el ID del traslado y actualizaremos su estado en la BD

        // Mantenemos tu redirección intacta
        redirigir('/traslados/detalle');
    }
}