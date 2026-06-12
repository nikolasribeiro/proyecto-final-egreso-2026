<?php

declare(strict_types=1);

namespace Nucleo\Constantes;

/**
 * Plantillas de encuestas. La plantilla "fnr" reproduce los 4 puntos
 * exactos que audita el FNR (Fondo Nacional de Recursos).
 *
 * Los textos son HARDCODED por requerimiento del cliente y deben
 * coincidir literalmente con el formulario oficial.
 */
final class PlantillasEncuestas
{
    /**
     * Devuelve todas las plantillas disponibles.
     *
     * Cada plantilla tiene:
     *   - id       : clave única
     *   - nombre   : nombre legible para el selector
     *   - preguntas: array con 4 entradas; cada una con texto + extremos
     *
     * @return array<string, array<string, mixed>>
     */
    public static function todas(): array
    {
        return [
            'general' => [
                'id'     => 'general',
                'nombre' => 'Encuesta General',
                'descripcion' => 'Encuesta estándar de satisfacción del paciente.',
                'preguntas' => [
                    [
                        'id'      => 'hoteleria',
                        'texto'   => 'Hotelería',
                        'min'     => 1,
                        'max'     => 10,
                        'minLabel' => 'Insuficiente',
                        'maxLabel' => 'Excelente',
                    ],
                    [
                        'id'      => 'tiempo_atencion',
                        'texto'   => 'Tiempo de Atención',
                        'min'     => 1,
                        'max'     => 10,
                        'minLabel' => 'Muy lento',
                        'maxLabel' => 'Muy rápido',
                    ],
                    [
                        'id'      => 'trato_humanizado',
                        'texto'   => 'Trato Humanizado',
                        'min'     => 1,
                        'max'     => 10,
                        'minLabel' => 'Indiferente',
                        'maxLabel' => 'Excelente',
                    ],
                    [
                        'id'      => 'satisfaccion_inquietudes',
                        'texto'   => 'Satisfacción de Inquietudes',
                        'min'     => 1,
                        'max'     => 10,
                        'minLabel' => 'No resueltas',
                        'maxLabel' => 'Totalmente resueltas',
                    ],
                ],
            ],

            // ==== Plantilla FNR (auditada) ====
            'fnr' => [
                'id'     => 'fnr',
                'nombre' => 'Auditoría FNR',
                'descripcion' => 'Plantilla oficial del Fondo Nacional de Recursos.',
                'preguntas' => [
                    [
                        'id'      => 'hoteleria',
                        'texto'   => 'Calidad del servicio de hotelería recibido durante la internación',
                        'min'     => 1,
                        'max'     => 10,
                        'minLabel' => 'Muy deficiente',
                        'maxLabel' => 'Óptimo',
                    ],
                    [
                        'id'      => 'tiempo_atencion',
                        'texto'   => 'Oportunidad en el tiempo de atención desde el ingreso hasta el egreso',
                        'min'     => 1,
                        'max'     => 10,
                        'minLabel' => 'Inaceptable',
                        'maxLabel' => 'Excelente',
                    ],
                    [
                        'id'      => 'trato_humanizado',
                        'texto'   => 'Percepción del trato humanizado brindado por el equipo de salud',
                        'min'     => 1,
                        'max'     => 10,
                        'minLabel' => 'Insatisfactorio',
                        'maxLabel' => 'Plenamente satisfactorio',
                    ],
                    [
                        'id'      => 'satisfaccion_inquietudes',
                        'texto'   => 'Nivel de satisfacción con la respuesta obtenida a sus inquietudes',
                        'min'     => 1,
                        'max'     => 10,
                        'minLabel' => 'Nada satisfecho',
                        'maxLabel' => 'Completamente satisfecho',
                    ],
                ],
            ],
        ];
    }

    /**
     * Devuelve una plantilla por id. Si no existe devuelve la "general".
     */
    public static function obtener(string $id): array
    {
        return self::todas()[$id] ?? self::todas()['general'];
    }
}
