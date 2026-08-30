<?php
namespace Controladores;

use Modelos\ModeloEncuesta;
use Modelos\ModeloAuditoria;
use Nucleo\Constantes\PlantillasEncuestas; 

class ControladorEncuestaPublica {
    
    public function mostrar(string $token): void {
        $modelo = new ModeloEncuesta();
        $encuesta = $modelo->obtenerPorToken($token);

        if (!$encuesta) {
            echo "El enlace de esta encuesta es inválido o ha caducado.";
            return;
        }

        $plantillas = PlantillasEncuestas::todas(); 
        $encuesta['preguntas'] = $plantillas['general']['preguntas'] ?? [];

        vista('public/encuesta_mobile', ['encuesta' => $encuesta]);
    }

    public function enviar(string $token): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        $modelo = new ModeloEncuesta();
        $encuesta = $modelo->obtenerPorToken($token);
        
        if ($encuesta) {
            $respuestasDetalle = [];
            $sumaCalificaciones = 0;
            $cantidadPreguntas = 0;

            foreach ($_POST as $key => $value) {
                if (strpos($key, 'p_') === 0) {
                    $numeroPregunta = (int) str_replace('p_', '', $key);
                    $valor = (int) $value;
                    $respuestasDetalle[$numeroPregunta] = $valor;
                    $sumaCalificaciones += $valor;
                    $cantidadPreguntas++;
                }
            }

            $calificacionGeneral = $cantidadPreguntas > 0 ? (int) round($sumaCalificaciones / $cantidadPreguntas) : 0;

            $data = [
                'id_encuesta' => $encuesta['id'],
                'ci_usuario' => null, 
                'calificacion' => $calificacionGeneral,
                'respuestas_detalle' => $respuestasDetalle
            ];

            if ($modelo->guardarRespuestas($data)) {
                $modeloAuditoria = new ModeloAuditoria();
                $modeloAuditoria->registrar('CREAR', 'respuestas_encuesta', 'Encuesta pública respondida vía token (Totalmente Anónima)', null);
            }
            
            header('Location: /encuesta/gracias');
            exit;
        }
    }

    public function gracias(): void {
        vista('public/agradecimiento', []);
    }
}