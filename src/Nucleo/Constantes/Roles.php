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
            ],
            'medico' => [
                'traslados'  => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
                'documentos' => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
                'encuestas'  => ['ver' => true, 'crear' => true,  'editar' => false, 'eliminar' => false],
                'usuarios'   => ['ver' => false, 'crear' => false, 'editar' => false, 'eliminar' => false],
                'permisos'   => ['ver' => false, 'crear' => false, 'editar' => false, 'eliminar' => false],
            ],
            'enfermero' => [
                'traslados'  => ['ver' => true, 'crear' => true,  'editar' => false, 'eliminar' => false],
                'documentos' => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
                'encuestas'  => ['ver' => true, 'crear' => true,  'editar' => false, 'eliminar' => false],
                'usuarios'   => ['ver' => false, 'crear' => false, 'editar' => false, 'eliminar' => false],
                'permisos'   => ['ver' => false, 'crear' => false, 'editar' => false, 'eliminar' => false],
            ],
            'soporte_tecnico' => [
                'traslados'  => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
                'documentos' => ['ver' => true, 'crear' => true,  'editar' => true,  'eliminar' => true],
                'encuestas'  => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
                'usuarios'   => ['ver' => true, 'crear' => false, 'editar' => true,  'eliminar' => true],
                'permisos'   => ['ver' => false, 'crear' => false, 'editar' => false, 'eliminar' => false],
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
            'administrador'  => 'Administrador',
            'medico'         => 'Médico',
            'enfermero'      => 'Enfermero',
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
}
