<?php

namespace Controladores;

use Modelos\ModeloAuditoria;
use Nucleo\Vista;

class ControladorAuditoria {
    
    public function inicio() {
        $modelo = new ModeloAuditoria();
        $logs = $modelo->obtenerLogs();

        Vista::mostrar('modulos/auditoria/inicio', [
            'logs' => $logs
        ]);
    }
}