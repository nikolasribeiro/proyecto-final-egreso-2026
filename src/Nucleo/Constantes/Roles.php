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
     * Cache lazy de permisos efectivos leídos desde BD (issue #130).
     *
     * Estructura: [ui_key][recurso][accion] => bool. Se llena en la
     * primera llamada a `permiso()` y se conserva por proceso PHP. Las
     * mutaciones (`ModeloPermiso::alternar`) llaman a
     * `Roles::invalidarCachePermisos()` para forzar la recarga.
     *
     * Limitación conocida en PHP-FPM multi-worker: cada worker tiene su
     * propio cache, así que un toggle puede tardar unos segundos en
     * propagarse a todos los workers hasta que sus caches expiren. Para
     * la v1 aceptamos esa ventana — se reemplaza por APCu o Redis en
     * una iteración posterior si hace falta consistencia estricta.
     *
     * @var array<string, array<string, array<string, bool>>>|null
     */
    private static ?array $cachePermisosBd = null;

    /**
     * Marca el cache de BD como vencido. Llamado por
     * `ModeloPermiso::alternar()` después de una mutación exitosa.
     * Seguro de llamar en cualquier momento — el próximo acceso
     * simplemente re-llenará el cache.
     */
    public static function invalidarCachePermisos(): void
    {
        self::$cachePermisosBd = null;
    }

    /**
     * Carga el cache de permisos desde BD. Idempotente: si ya está
     * cargado, no hace nada. Si la BD no responde o la tabla está
     * vacía, deja el cache en [] (todos los permisos caen al fallback
     * hardcoded).
     */
    private static function cargarCachePermisosBd(): void
    {
        if (self::$cachePermisosBd !== null) {
            return;
        }

        self::$cachePermisosBd = [];
        try {
            $modelo = new \Modelos\ModeloPermiso();
            $bd = $modelo->obtenerMatriz();      // [id_rol][recurso][accion] => bool
            $roles = $modelo->obtenerRoles();    // [id_rol] => tipo_rol enum

            foreach ($bd as $idRol => $recursos) {
                $uiKey = self::mapEnumToUi((string)($roles[$idRol] ?? ''));
                if ($uiKey !== null) {
                    self::$cachePermisosBd[$uiKey] = $recursos;
                }
            }
        } catch (\Throwable $e) {
            // No rompemos la app si la BD no está disponible: caemos
            // al fallback hardcoded. Logueamos para diagnóstico.
            error_log('Roles::permiso: no se pudo cargar cache BD: ' . $e->getMessage());
            self::$cachePermisosBd = [];
        }
    }

    /**
     * Devuelve la matriz completa de permisos hardcodeada.
     *
     * Es la fuente de verdad para el seed de `permisos_rol` y el
     * fallback de `permiso()`. La matriz dinámica vive en BD — la
     * `permiso()` consulta BD primero y solo cae a esta constante si
     * no hay override.
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
            'superadministrativo' => [
                // Root: TODO true. Cubre traslados, documentos, encuestas,
                // usuarios, permisos y vehículos. La UI de matriz permite
                // togglear estas celdas desde BD igual que con cualquier
                // otro rol; si se apaga una, queda apagada hasta nuevo
                // toggle.
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
            'administrador'      => 'Administrador',
            'superadministrativo' => 'SuperAdministrativo',
            'medico'             => 'Médico',
            'enfermero'          => 'Enfermero',
            'chofer'             => 'Chofer',
            'soporte_tecnico'    => 'Soporte Técnico',
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
     *
     * Orden de resolución (issue #130):
     *   1. Cache lazy de BD (`permisos_rol`). Si la celda está definida,
     *      ese valor gana.
     *   2. Fallback a la constante hardcodeada `matriz()`.
     *
     * Si la BD está vacía o no responde, todo cae al fallback — la app
     * sigue funcionando con los defaults hasta que se siembren toggles
     * desde la UI.
     */
    public static function permiso(string $rol, string $recurso, string $accion): bool
    {
        self::cargarCachePermisosBd();

        // 1. Override desde BD.
        if (isset(self::$cachePermisosBd[$rol][$recurso][$accion])) {
            return (bool)self::$cachePermisosBd[$rol][$recurso][$accion];
        }

        // 2. Fallback hardcoded.
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
        'administrador'      => 'ADMINISTRATIVO',
        'superadministrativo' => 'SUPERADMINISTRATIVO',
        'medico'             => 'MEDICO',
        'enfermero'          => 'ENFERMERO',
        'chofer'             => 'CHOFER',
        'soporte_tecnico'    => 'SOPORTE_TECNICO',
    ];

    /**
     * Mapa inverso (enum SQL → UI key). Usado para hidratar la sesión,
     * badges y respuestas JSON que esperan la clave canónica de la UI.
     *
     * @var array<string, string>
     */
    public const MAPA_ENUM_A_UI = [
        'ADMINISTRATIVO'     => 'administrador',
        'SUPERADMINISTRATIVO' => 'superadministrativo',
        'MEDICO'             => 'medico',
        'ENFERMERO'          => 'enfermero',
        'CHOFER'             => 'chofer',
        'SOPORTE_TECNICO'    => 'soporte_tecnico',
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

    /**
     * Indica si una UI key corresponde a un rol válido del catálogo.
     *
     * Se usa como guard de "rol utilizable" en el login y en el sidebar:
     * un usuario sin filas en `usuario_roles` (o con un rol legacy que ya
     * no existe) cae por acá y se le niega el acceso en lugar de caer
     * a un fallback fantasma como 'usuario'.
     */
    public static function esValido(string $rol): bool
    {
        return isset(self::MAPA_UI_A_ENUM[$rol]);
    }

    /**
     * Roles "reservados" que NO se pueden asignar desde la pantalla de
     * usuarios ni aparecen como toggleable en la matriz de permisos.
     *
     * El único rol reservado hoy es `superadministrativo` — el root del
     * sistema. Solo se asigna por seed / script de bootstrap; el resto
     * del tiempo debe seguir existiendo en `roles` y `permisos_rol` con
     * todos sus permisos, pero oculto de la UI para que ningún admin
     * pueda promoverse a sí mismo ni promover a otros desde la app.
     *
     * Si en el futuro se reserva otro rol, agregar la UI key a este array.
     *
     * @var array<int, string>
     */
    public const ROLES_RESERVADOS = [
        'superadministrativo',
    ];

    /**
     * Lista de UI keys que pueden asignarse desde la UI (crear / editar
     * usuarios, matriz de permisos).
     *
     * Es el subconjunto de `MAPA_UI_A_ENUM` que NO está en
     * `ROLES_RESERVADOS`. La fuente de verdad única del filtro es esta
     * constante — el resto de los lugares (controladores, vistas,
     * componentes) la consumen vía `rolesAsignables()` /
     * `esAsignable()` en lugar de hardcodear el nombre del rol.
     *
     * @return array<int, string>
     */
    public static function rolesAsignables(): array
    {
        return array_values(array_filter(
            array_keys(self::MAPA_UI_A_ENUM),
            static fn(string $uiKey): bool => !in_array($uiKey, self::ROLES_RESERVADOS, true),
        ));
    }

    /**
     * Indica si una UI key puede asignarse desde la UI. Devuelve `false`
     * para roles reservados (ej: `superadministrativo`) y para keys que
     * no existen en el catálogo.
     *
     * Útil como guard server-side para descartar silenciosamente un
     * `roles[]` que venga por POST en `usuarioCrear()` /
     * `usuarioActualizar()` aunque el campo del form esté oculto.
     */
    public static function esAsignable(string $rol): bool
    {
        return isset(self::MAPA_UI_A_ENUM[$rol])
            && !in_array($rol, self::ROLES_RESERVADOS, true);
    }
}
