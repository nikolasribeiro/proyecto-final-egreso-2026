<?php
namespace Controladores;

use Modelos\ModeloAuditoria;
use Nucleo\Vista;

class ControladorAuditoria {
    public function inicio() {
        try {
            $modelo = new ModeloAuditoria();
            $logs = $modelo->obtenerLogs();

            Vista::mostrar('modulos/auditoria/inicio', [
                'logs' => $logs
            ]);
            
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