<?php

namespace Modelos;

class ModeloTraslado
{
    private static array $traslados = [];

    public static function obtenerPorId(int $id): ?array
    {
        self::inicializarMockData();

        foreach (self::$traslados as $traslado) {
            if ($traslado['id'] === $id) {
                return $traslado;
            }
        }

        return null;
    }

    public static function obtenerTodos(): array
    {
        self::inicializarMockData();

        return array_values(self::$traslados);
    }

    public static function registrarArribo(int $trasladoId, int $destinoOrden, string $timestamp): array
    {
        $traslado = self::obtenerPorId($trasladoId);

        if (!$traslado) {
            return ['success' => false, 'message' => 'Traslado no encontrado'];
        }

        foreach ($traslado['destinos'] as&$destino) {
            if ($destino['orden'] === $destinoOrden) {
                $destino['tiempo_real'] = $timestamp;

                $estimada = strtotime($destino['tiempo_estimado']);
                $real = strtotime($timestamp);
                $destino['diferencia_minutos'] = round(($real - $estimada) / 60);

                return ['success' => true, 'data' => $destino];
            }
        }

        return ['success' => false, 'message' => 'Destino no encontrado'];
    }

    public static function crearReporte(int $trasladoId, int $destinoOrden, string $tipoProblema, string $mensaje): array
    {
        $traslado = self::obtenerPorId($trasladoId);

        if (!$traslado) {
            return ['success' => false, 'message' => 'Traslado no encontrado'];
        }

        foreach ($traslado['destinos'] as &$destino) {
            if ($destino['orden'] === $destinoOrden) {
                $reporte = [
                    'id' => count($destino['reportes']) + 1,
                    'tipo' => $tipoProblema,
                    'mensaje' => $mensaje,
                    'creado_en' => date('Y-m-d H:i:s')
                ];

                $destino['reportes'][] = $reporte;

                return ['success' => true, 'data' => $reporte];
            }
        }

        return ['success' => false, 'message' => 'Destino no encontrado'];
    }

    public static function cancelar(int $trasladoId, int $destinoOrden, string $tipoProblema, string $mensaje): array
    {
        $traslado = self::obtenerPorId($trasladoId);

        if (!$traslado) {
            return ['success' => false, 'message' => 'Traslado no encontrado'];
        }

        self::crearReporte($trasladoId, $destinoOrden, $tipoProblema, $mensaje);

        $traslado['estado'] = 'cancelado';
        $traslado['motivo_cancelacion'] = $mensaje;

        return ['success' => true, 'message' => 'Traslado cancelado'];
    }

    public static function avanzarPaso(int $trasladoId): array
    {
        $traslado = self::obtenerPorId($trasladoId);

        if (!$traslado) {
            return ['success' => false, 'message' => 'Traslado no encontrado'];
        }

        $traslado['paso_actual']++;

        return ['success' => true, 'data' => $traslado];
    }

    private static function inicializarMockData(): void
    {
        if (!empty(self::$traslados)) {
            return;
        }

        self::$traslados = [
1 => [
                'id' => 1,
                'numero' => 'TRF-2024-0891',
                'tipo' => 'paciente_alta',
                'estado' => 'en_proceso',
                'paciente' => 'Juan Pérez',
                'conductor' => 'Carlos López',
                'vehiculo' => 'Ambulancia 001',
                'origen' => 'Hospital Central',
                'volver_al_origen' => true,
                'paso_actual' => 2,
                'destinos' => [
                    [
                        'orden' => 1,
                        'nombre' => 'Hospital Norte',
                        'tiempo_estimado' => '2024-06-15T14:00:00',
                        'tiempo_real' => '2024-06-15T14:05:00',
                        'diferencia_minutos' => 5,
                        'reportes' => [
                            ['id' => 1, 'tipo' => 'Daño mecánico', 'mensaje' => 'Pinchazo en ruta'],
                            ['id' => 2, 'tipo' => 'Retraso en ruta', 'mensaje' => 'Trufficazo en Av. Principal']
                        ]
                    ],
                    [
                        'orden' => 2,
                        'nombre' => 'Clínica Sur',
                        'tiempo_estimado' => '2024-06-15T15:30:00',
                        'tiempo_real' => null,
                        'diferencia_minutos' => null,
                        'reportes' => []
                    ]
                ]
            ],
            2 => [
                'id' => 2,
                'numero' => 'TRF-2024-0892',
                'tipo' => 'biologico',
                'estado' => 'en_proceso',
                'paciente' => 'María García',
                'conductor' => 'Pedro Rodríguez',
                'vehiculo' => 'Ambulancia 002',
                'origen' => 'Hospital Central',
                'volver_al_origen' => false,
                'paso_actual' => 1,
                'destinos' => [
                    [
                        'orden' => 1,
                        'nombre' => 'Instituto Nacional de Cardiología',
                        'tiempo_estimado' => '2024-06-15T16:00:00',
                        'tiempo_real' => null,
                        'diferencia_minutos' => null,
                        'reportes' => []
                    ]
                ]
            ],
            3 => [
                'id' => 3,
                'numero' => 'TRF-2024-0893',
                'tipo' => 'equipamiento',
                'estado' => 'completado',
                'paciente' => 'Carlos Mendez',
                'conductor' => 'Roberto Sánchez',
                'vehiculo' => 'Ambulancia 003',
                'origen' => 'Hospital Central',
                'volver_al_origen' => true,
                'paso_actual' => 6,
                'destinos' => [
                    [
                        'orden' => 1,
                        'nombre' => 'Hospital南区',
                        'tiempo_estimado' => '2024-06-15T10:00:00',
                        'tiempo_real' => '2024-06-15T10:08:00',
                        'diferencia_minutos' => 8,
                        'reportes' => [
                            ['id' => 1, 'tipo' => 'Retraso en ruta', 'mensaje' => 'Semáforo en Av. Principal']
                        ]
                    ],
                    [
                        'orden' => 2,
                        'nombre' => 'Clínica Central',
                        'tiempo_estimado' => '2024-06-15T11:30:00',
                        'tiempo_real' => '2024-06-15T11:25:00',
                        'diferencia_minutos' => -5,
                        'reportes' => []
                    ]
                ]
            ]
        ];
    }
}
