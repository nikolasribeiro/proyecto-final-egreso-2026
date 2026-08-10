<?php

declare(strict_types=1);

namespace Nucleo\Constantes;

/**
 * Matriz de permisos por rol.
 *
 * Estructura: [rol] => [recurso] => [accion] => bool
 *
 * Recursos: traslados, documentos, encuestas, usuarios, permisos
 * Acciones: ver, crear, editar, eliminar
 */
final class Roles
{
    /**
     * Devuelve la matriz completa de permisos.
     *
     * TODO(#130): los permisos por rol sobre cada recurso deben pasar a la
     * matriz interactiva en BD. Hasta entonces, ajustar acá implica commit.
     *
     * @return array<string, array<string, array<string, bool>>>
     */
    public static function matriz(): array
    {
        return [
            'administrador' => [
                'traslados'  => ['ver' => true, 'crear' => true,  'editar' => true,  'eliminar' => true],
                'documentos' => ['ver' => true, 'crear' => true,  'editar' => true,  'eliminar' => true],
                'encuestas'  => ['ver' => true, 'crear' => true,  'editar' => true,  'eliminar' => true],
                'usuarios'   => ['ver' => true, 'crear' => true,  'editar' => true,  'eliminar' => true],
                'permisos'   => ['ver' => true, 'crear' => true,  'editar' => true,  'eliminar' => true],
                'vehiculos'  => ['ver' => true, 'crear' => true,  'editar' => true,  'eliminar' => true],
            ],
            'medico' => [
                'traslados'  => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
                'documentos' => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
                'encuestas'  => ['ver' => true, 'crear' => true,  'editar' => false, 'eliminar' => false],
                'usuarios'   => ['ver' => false, 'crear' => false, 'editar' => false, 'eliminar' => false],
                'permisos'   => ['ver' => false, 'crear' => false, 'editar' => false, 'eliminar' => false],
                'vehiculos'  => ['ver' => false, 'crear' => false, 'editar' => false, 'eliminar' => false],
            ],
            'enfermero' => [
                'traslados'  => ['ver' => true, 'crear' => true,  'editar' => false, 'eliminar' => false],
                'documentos' => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
                'encuestas'  => ['ver' => true, 'crear' => true,  'editar' => false, 'eliminar' => false],
                'usuarios'   => ['ver' => false, 'crear' => false, 'editar' => false, 'eliminar' => false],
                'permisos'   => ['ver' => false, 'crear' => false, 'editar' => false, 'eliminar' => false],
                'vehiculos'  => ['ver' => false, 'crear' => false, 'editar' => false, 'eliminar' => false],
            ],
            'chofer' => [
                'traslados'  => ['ver' => true, 'crear' => true,  'editar' => true,  'eliminar' => false],
                'documentos' => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
                'encuestas'  => ['ver' => false, 'crear' => false, 'editar' => false, 'eliminar' => false],
                'usuarios'   => ['ver' => false, 'crear' => false, 'editar' => false, 'eliminar' => false],
                'permisos'   => ['ver' => false, 'crear' => false, 'editar' => false, 'eliminar' => false],
                'vehiculos'  => ['ver' => false, 'crear' => false, 'editar' => false, 'eliminar' => false],
            ],
            'soporte_tecnico' => [
                'traslados'  => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
                'documentos' => ['ver' => true, 'crear' => true,  'editar' => true,  'eliminar' => true],
                'encuestas'  => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
                'usuarios'   => ['ver' => true, 'crear' => false, 'editar' => true,  'eliminar' => true],
                'permisos'   => ['ver' => false, 'crear' => false, 'editar' => false, 'eliminar' => false],
                'vehiculos'  => ['ver' => true,  'crear' => false, 'editar' => false, 'eliminar' => false],
            ],
        ];
    }

    /**
     * Lista de roles disponibles (legible para UI).
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'administrador'   => 'Administrador',
            'medico'          => 'Médico',
            'enfermero'       => 'Enfermero',
            'chofer'          => 'Chofer',
            'soporte_tecnico' => 'Soporte Técnico',
        ];
    }

    /**
     * Lista de acciones para la tabla de matriz.
     *
     * @return array<string, string>
     */
    public static function acciones(): array
    {
        return [
            'ver'     => 'Ver',
            'crear'   => 'Crear',
            'editar'  => 'Editar',
            'eliminar' => 'Eliminar',
        ];
    }

    /**
     * Lista de recursos para la tabla de matriz.
     *
     * @return array<string, string>
     */
    public static function recursos(): array
    {
        return [
            'traslados'  => 'Traslados',
            'documentos' => 'Documentos',
            'encuestas'  => 'Encuestas',
            'usuarios'   => 'Usuarios',
            'permisos'   => 'Permisos',
            'vehiculos'  => 'Vehículos',
        ];
    }

    /**
     * Devuelve el permiso (true/false) para un rol/recurso/acción.
     * Si el rol/recurso/acción no existe, devuelve false.
     */
    public static function permiso(string $rol, string $recurso, string $accion): bool
    {
        $matriz = self::matriz();
        return $matriz[$rol][$recurso][$accion] ?? false;
    }

    /**
     * Mapa canónico UI key (`administrador`, etc.) → enum SQL
     * (`roles.tipo_rol`, ej. `ADMINISTRATIVO`).
     *
     * Es el único punto de traducción entre los dos mundos. Todos los
     * modelos, seeders y queries DEBEN pasar por acá. No hardcodear el
     * enum en string literal en ningún otro lado (issue #115).
     *
     * Si en el futuro el enum se renombra para coincidir 1:1 con las
     * claves PHP (ej. `ADMINISTRATIVO` → `ADMINISTRADOR`), alcanza con
     * tocar este mapa y la migración SQL — el resto del código sigue
     * funcionando sin cambios.
     *
     * @var array<string, string>
     */
    public const MAPA_UI_A_ENUM = [
        'administrador'   => 'ADMINISTRATIVO',
        'medico'          => 'MEDICO',
        'enfermero'       => 'ENFERMERO',
        'chofer'          => 'CHOFER',
        'soporte_tecnico' => 'SOPORTE_TECNICO',
    ];

    /**
     * Mapa inverso (enum SQL → UI key). Usado para hidratar la sesión,
     * badges y respuestas JSON que esperan la clave canónica de la UI.
     *
     * @var array<string, string>
     */
    public const MAPA_ENUM_A_UI = [
        'ADMINISTRATIVO'  => 'administrador',
        'MEDICO'          => 'medico',
        'ENFERMERO'       => 'enfermero',
        'CHOFER'          => 'chofer',
        'SOPORTE_TECNICO' => 'soporte_tecnico',
    ];

    /**
     * Traduce una UI key (ej. `administrador`) al enum SQL
     * (`ADMINISTRATIVO`). Devuelve null si la key no existe en el
     * catálogo — el caller debe tratarlo como input inválido.
     */
    public static function mapUiToEnum(string $uiKey): ?string
    {
        return self::MAPA_UI_A_ENUM[$uiKey] ?? null;
    }

    /**
     * Traduce un enum SQL (ej. `ADMINISTRATIVO`) a la UI key
     * (`administrador`). Devuelve null si el enum no está mapeado
     * (útil para defenderse de filas legacy con valores viejos).
     */
    public static function mapEnumToUi(string $enum): ?string
    {
        return self::MAPA_ENUM_A_UI[$enum] ?? null;
    }

    /**
     * Lista de UI keys válidas (las mismas que `labels()`).
     *
     * @return array<int, string>
     */
    public static function uiKeysValidas(): array
    {
        return array_keys(self::MAPA_UI_A_ENUM);
    }
}
