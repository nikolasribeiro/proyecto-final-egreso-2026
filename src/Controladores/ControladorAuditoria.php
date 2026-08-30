<?php

namespace Controladores;

use Modelos\ModeloAuditoria;
use Nucleo\RutaProtegida;
use Nucleo\Sesion;

class ControladorAuditoria extends RutaProtegida
{
    private string $nombre_usuario;
    private string $rol;

    public function __construct()
    {
        // Hereda el guard de RutaProtegida (sesión activa + roles válidos).
        parent::__construct();

        $usuario = Sesion::obtener('user');
        $this->nombre_usuario = $usuario['nombre'];
        $this->rol = $usuario['rol'];
    }


    public function inicio()
    {
        try {
            $modelo = new ModeloAuditoria();
            $logs = $modelo->obtenerLogs();

            vista('modulos/auditoria/inicio', [
                'logs' => $logs,
                'titulo_pagina' => "Auditoria",
                'nombre' => $this->nombre_usuario,
                'rol' => $this->rol,
            ], "admin");
        } catch (\Throwable $e) {
            // Esto atrapará cualquier error fatal y lo imprimirá en pantalla
            echo "<div style='padding: 20px; background: #ffebee; color: #c62828; font-family: sans-serif;'>";
            echo "<h2>¡Te atrapé, error 500!</h2>";
            echo "<strong>Mensaje:</strong> " . $e->getMessage() . "<br><br>";
            echo "<strong>Archivo:</strong> " . $e->getFile() . " (Línea " . $e->getLine() . ")";
            echo "</div>";
        }
    }
}
