<?php

namespace Controladores;

use Nucleo\Sesion;
use Nucleo\RutaProtegida;
use Nucleo\Constantes\Roles;
use Nucleo\Constantes\Usuarios;
use Nucleo\Constantes\PlantillasEncuestas;
use Modelos\ModeloTraslado;

class ControladorDashboard extends RutaProtegida
{
    private string $nombre_usuario;
    private string $rol;
    private ModeloTraslado $modelo_traslado;

    /**
     * Mock de documentos. Incluye el campo `categoria` (slug + nombre legible)
     * que se usa para agrupar y para construir la URL del QR.
     */
    private const DOCUMENTOS = [
        [
            'id' => 'TRF-2024-0891',
            'nombre' => 'Protocolo de Emergencias 2024',
            'tipo' => 'PDF',
            'tamano' => '2.4 MB',
            'fecha_subida' => 'Hace 2 dias',
            'ruta' => '/uploads/protocolo_emergencia.pdf',
            'categoria' => ['slug' => 'cardiologia', 'nombre' => 'Cardiología'],
        ],
        [
            'id' => 'TRF-2024-0892',
            'nombre' => 'Guia de Traslado 2024',
            'tipo' => 'PDF',
            'tamano' => '1.5 MB',
            'fecha_subida' => 'Hace 3 dias',
            'ruta' => '/uploads/guia_traslado.pdf',
            'categoria' => ['slug' => 'cardiologia', 'nombre' => 'Cardiología'],
        ],
        [
            'id' => 'TRF-2024-0893',
            'nombre' => 'Plan de Salud 2024',
            'tipo' => 'PDF',
            'tamano' => '1.8 MB',
            'fecha_subida' => 'Hace 5 dias',
            'ruta' => '/uploads/plan_salud.pdf',
            'categoria' => ['slug' => 'administracion', 'nombre' => 'Administración'],
        ],
        [
            'id' => 'TRF-2024-0894',
            'nombre' => 'Protocolo de Bioseguridad',
            'tipo' => 'PDF',
            'tamano' => '1.1 MB',
            'fecha_subida' => 'Hace 1 semana',
            'ruta' => '/uploads/protocolo_bioseguridad.pdf',
            'categoria' => ['slug' => 'administracion', 'nombre' => 'Administración'],
        ],
    ];

    public function __construct()
    {
        parent::__construct();

        $usuario = Sesion::obtener('user');
        $this->modelo_traslado = new ModeloTraslado();
        $this->nombre_usuario = $usuario['nombre'];
        $this->rol = $usuario['rol'];
    }

    /**
     * Muestra el modulo documentos
     */
    public function documentos(): void
    {
        vista('modulos/documentos/inicio', [
            'titulo_pagina' => "Gestion de Documentos",
            'nombre' => $this->nombre_usuario,
            'rol' => $this->rol,
            'documentos' => self::DOCUMENTOS
        ], 'admin');
    }

    /**
     * Muestra los PDFs de una categoría específica.
     * Esta vista es la que abre el QR desde el celular.
     */
    public function documentosCategoria(string $slug): void
    {
        $documentos = array_values(array_filter(
            self::DOCUMENTOS,
            fn($d) => $d['categoria']['slug'] === $slug
        ));

        // Buscar el nombre legible de la categoría
        $nombreCategoria = 'Categoría desconocida';
        foreach (self::DOCUMENTOS as $doc) {
            if ($doc['categoria']['slug'] === $slug) {
                $nombreCategoria = $doc['categoria']['nombre'];
                break;
            }
        }

        vista('modulos/documentos/categoria', [
            'titulo_pagina' => "Categoría: $nombreCategoria",
            'nombre' => $this->nombre_usuario,
            'rol' => $this->rol,
            'slug' => $slug,
            'nombreCategoria' => $nombreCategoria,
            'documentos' => $documentos,
        ], 'admin');
    }

    /**
     * Muestra el modulo traslados
     */
    public function trasladosInicio(): void
    {
        // Filtro via query param: ?filtro=todos|activos|completados.
        // Default 'todos'. Cualquier valor no reconocido cae al default.
        $filtro = $_GET['filtro'] ?? 'todos';
        if (!in_array($filtro, ['todos', 'activos', 'completados'], true)) {
            $filtro = 'todos';
        }

        // Filtro de prioridades via query param: ?prioridades=verde,amarillo,rojo
        // (lista separada por comas). Default = las 3 prioridades activas.
        // Valores desconocidos se descartan.
        $todasPrioridades = ['verde', 'amarillo', 'rojo'];
        if (isset($_GET['prioridades'])) {
            $entradas = array_filter(
                array_map('trim', explode(',', (string)$_GET['prioridades'])),
                fn($v) => $v !== '',
            );
            $prioridadesActivas = array_values(array_intersect($entradas, $todasPrioridades));
            // Si la URL explicitamente envió el param pero quedó vacío tras
            // el filtrado, eso significa "ninguna activa" → mantenemos array
            // vacío (a diferencia del caso "no enviar param" que es default).
            if (empty($prioridadesActivas) && !empty($entradas)) {
                $prioridadesActivas = [];
            }
        } else {
            // Sin param en URL: default = todas activas (no filtrar).
            $prioridadesActivas = $todasPrioridades;
        }

        $trasladosRaw = $this->modelo_traslado->obtenerTodos($filtro, $prioridadesActivas);

        // Mapear al formato que espera la vista con datos reales del modelo
        $traslados = array_map(function ($t) {
            $tipoLegible = match ($t['tipo'] ?? 'paciente_alta') {
                'paciente_alta' => 'Paciente',
                'biologico'     => 'Biológico',
                'equipamiento'  => 'Equipamiento',
                default         => ucfirst((string)$t['tipo']),
            };
            // Etiquetas semánticas (no colores literales) — consistentes con
            // la página de detalle.
            $prioridadLegible = match ($t['prioridad'] ?? 'verde') {
                'rojo'     => 'EMERGENCIA',
                'amarillo' => 'URGENTE',
                'verde'    => 'RUTINARIO',
                default    => 'RUTINARIO',
            };
            $estadoInterno = strtolower($t['estado_nombre'] ?? 'pendiente');
            // Labels humanizados para el chip de estado.
            $estadoLegible = match ($estadoInterno) {
                'pendiente'   => 'Esperando inicio',
                'en_transito' => 'En curso',
                'finalizado'  => 'Completado',
                'cancelado'   => 'Cancelado',
                default       => ucfirst($estadoInterno),
            };
            // Clase CSS modifier para el color del chip — coincide con los
            // selectores ya definidos en badges.css.
            $estadoClaseCss = match ($estadoInterno) {
                'pendiente'   => 'status-pending',
                'en_transito' => 'status-in-progress',
                'finalizado'  => 'status-completed',
                'cancelado'   => 'status-cancelled',
                default       => '',
            };
            return [
                'id'                => (string)$t['id'],
                'tipo'              => $tipoLegible,
                'tipo_interno'      => $t['tipo'] ?? 'paciente_alta',
                'ubicacion_origen'  => $t['origen'] ?? '-',
                'ubicacion_destino' => $t['destinos_texto'] ?? 'Sin destino',
                'fecha_realizacion' => $t['fecha_hora_salida'] ?? '-',
                'chofer'            => $t['conductor'] ?? '-',
                'estado'            => $estadoLegible,
                'estado_interno'    => $estadoInterno,
                'estado_clase_css'  => $estadoClaseCss,
                'prioridad'         => $prioridadLegible,
                'prioridad_interna' => $t['prioridad'] ?? 'verde',
            ];
        }, $trasladosRaw);

        vista('modulos/traslados/inicio', [
            'titulo_pagina' => "Traslados",
            'nombre' => $this->nombre_usuario,
            'rol' => $this->rol,
            'traslados' => $traslados,
            'filtro_actual' => $filtro,
            'prioridades_activas' => $prioridadesActivas,
            'puede_crear' => Roles::permiso($this->rol, 'traslados', 'crear'),
        ], 'admin');
    }

    public function nuevoTraslado(): void
    {
        $choferes = $this->modelo_traslado->obtenerChoferesDisponibles();
        $enfermeros = $this->modelo_traslado->obtenerEnfermeros();
        $vehiculos  = $this->modelo_traslado->obtenerVehiculosDisponibles();
        $ubicaciones = $this->modelo_traslado->obtenerUbicaciones();

        vista('modulos/traslados/nuevo/inicio', [
            'titulo_pagina' => 'Nuevo Traslado',
            'nombre' => $this->nombre_usuario,
            'rol' => $this->rol,
            'choferes'      => $choferes,
            'enfermeros'    => $enfermeros,
            'vehiculos'     => $vehiculos,
            'ubicaciones'   => $ubicaciones,
            'csrf'          => Sesion::generarTokenCsrf(),
        ], 'admin');
    }

    public function detalleTraslado(int $id): void
    {
        $traslado = $id > 0 ? $this->modelo_traslado->obtenerPorId($id) : null;
        if (!$traslado) {
            abortar(404);
        }

        vista('modulos/traslados/detalle/inicio', [
            'titulo_pagina' => 'Detalle del Traslado #' . $id,
            'nombre'        => $this->nombre_usuario,
            'rol'           => $this->rol,
            'csrf'          => Sesion::generarTokenCsrf(),
            'traslado_id'   => (int)$traslado['id'],
            'traslado_data' => [
                'numero'           => 'TRF-' . $traslado['id'],
                'tipo'             => $traslado['tipo'] ?? 'paciente_alta',
                'paciente'         => $traslado['ci_paciente_externo'] ?? 'Traslado ' . $traslado['id'],
                'origen'           => $traslado['origen'] ?? '-',
                'conductor'        => $traslado['chofer_nombre'] ?? '-',
                'enfermero'        => $traslado['enfermero_nombre'] ?? null,
                'vehiculo'         => trim(($traslado['matricula'] ?? '') . ' — ' . ($traslado['tipo_vehiculo'] ?? '')),
                'estado'           => strtolower((string)($traslado['estado_nombre'] ?? 'PENDIENTE')),
                'estado_nombre'    => $traslado['estado_nombre'] ?? 'PENDIENTE',
                'prioridad'        => $traslado['prioridad'] ?? 'verde',
                'destinos'         => $traslado['destinos'] ?? [],
                'paso_info'        => $traslado['paso_info'] ?? null,
                'fecha_salida'     => $traslado['fecha_hora_salida'] ?? null,
                'volver_al_origen' => (bool)($traslado['volver_al_origen'] ?? false),
            ],
        ], 'admin');
    }

    // ==========================================
    // API METHODS
    // ==========================================

    /**
     * Lee el body JSON y valida CSRF.
     * Devuelve el array de datos si todo OK, o null si CSRF inválido
     * (en cuyo caso ya se envió la respuesta 419).
     */
    private function leerBodyConCsrf(): ?array
    {
        $raw = json_decode(file_get_contents('php://input'), true);
        if (!is_array($raw)) {
            $raw = [];
        }
        $csrf = $raw['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Sesion::validarTokenCsrf((string)$csrf)) {
            http_response_code(419);
            echo json_encode(['success' => false, 'message' => 'CSRF inválido']);
            return null;
        }
        return $raw;
    }

    public function apiCrearTraslado(): void
    {
        header('Content-Type: application/json');
        $data = $this->leerBodyConCsrf();
        if ($data === null) return;

        if (
            empty($data['tipo']) || empty($data['id_vehiculo']) ||
            empty($data['ci_chofer']) || empty($data['destinos'])
        ) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            return;
        }

        try {
            $user = Sesion::obtener('user');
            $data['ci_administrativo'] = $user['ci'] ?? 11111111; // fallback dev

            $id = $this->modelo_traslado->crearSolicitud($data);
            echo json_encode(['success' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Crea una ubicación (destino) nueva desde el modal del paso 4 del wizard.
     */
    public function apiCrearUbicacion(): void
    {
        header('Content-Type: application/json');
        $data = $this->leerBodyConCsrf();
        if ($data === null) return;

        if (!Roles::permiso($this->rol, 'traslados', 'crear')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tenés permisos para crear destinos']);
            return;
        }

        $nombre    = trim((string)($data['nombre'] ?? ''));
        $direccion = trim((string)($data['direccion'] ?? ''));

        if ($nombre === '' || $direccion === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            return;
        }

        if (mb_strlen($nombre) > 150 || mb_strlen($direccion) > 255) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Nombre o dirección demasiado largos']);
            return;
        }

        try {
            $ubicacion = $this->modelo_traslado->crearUbicacion($nombre, $direccion);
            echo json_encode(['success' => true, 'ubicacion' => $ubicacion]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function apiObtenerTraslado(int $id): void
    {
        header('Content-Type: application/json');

        $traslado = $this->modelo_traslado->obtenerPorId($id);

        if (!$traslado) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Traslado no encontrado']);
            return;
        }

        echo json_encode(['success' => true, 'data' => $traslado]);
    }

    public function apiRegistrarArribo(int $id): void
    {
        header('Content-Type: application/json');
        $data = $this->leerBodyConCsrf();
        if ($data === null) return;

        if (!isset($data['destino_orden']) || !isset($data['timestamp'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            return;
        }

        $resultado = $this->modelo_traslado->registrarArribo(
            $id,
            (int)$data['destino_orden'],
            (string)$data['timestamp']
        );

        echo json_encode($resultado);
    }

    public function apiRegistrarSalida(int $id): void
    {
        header('Content-Type: application/json');
        $data = $this->leerBodyConCsrf();
        if ($data === null) return;

        if (!isset($data['destino_orden'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            return;
        }

        $resultado = $this->modelo_traslado->registrarSalida(
            $id,
            (int)$data['destino_orden']
        );

        echo json_encode($resultado);
    }

    public function apiCrearReporte(int $id): void
    {
        header('Content-Type: application/json');
        $data = $this->leerBodyConCsrf();
        if ($data === null) return;

        if (!isset($data['destino_orden']) || !isset($data['tipo_problema']) || !isset($data['mensaje'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            return;
        }

        $resultado = $this->modelo_traslado->crearReporte(
            $id,
            (int)$data['destino_orden'],
            (string)$data['tipo_problema'],
            (string)$data['mensaje']
        );

        echo json_encode($resultado);
    }

    public function apiCancelarTraslado(int $id): void
    {
        header('Content-Type: application/json');
        $data = $this->leerBodyConCsrf();
        if ($data === null) return;

        if (!isset($data['destino_orden']) || !isset($data['tipo_problema']) || !isset($data['mensaje'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            return;
        }

        $resultado = $this->modelo_traslado->cancelar(
            $id,
            (int)$data['destino_orden'],
            (string)$data['tipo_problema'],
            (string)$data['mensaje']
        );

        echo json_encode($resultado);
    }

    // ==========================================
    // MÓDULO ENCUESTAS
    // ==========================================

    /**
     * Muestra el formulario de encuesta cuantitativa.
     */
    public function encuestas(): void
    {
        $plantillaSeleccionada = $_GET['plantilla'] ?? 'general';

        $flash = Sesion::obtener('flash_encuesta');
        Sesion::eliminar('flash_encuesta');

        vista('modulos/encuestas/inicio', [
            'titulo_pagina' => 'Encuestas de Satisfacción',
            'nombre' => $this->nombre_usuario,
            'rol' => $this->rol,
            'plantillas' => PlantillasEncuestas::todas(),
            'plantilla_seleccionada' => $plantillaSeleccionada,
            'encuesta' => PlantillasEncuestas::obtener($plantillaSeleccionada),
            'flash' => $flash,
        ], 'admin');
    }

    /**
     * Procesa el envío de una encuesta.
     */
    public function encuestaSubmit(): void
    {
        $plantillaId = $_POST['plantilla'] ?? 'general';
        $plantilla = PlantillasEncuestas::obtener($plantillaId);

        // Validar que las 4 preguntas tengan un valor 1..10
        $errores = [];
        foreach ($plantilla['preguntas'] as $pregunta) {
            $valor = $_POST['p_' . $pregunta['id']] ?? null;
            if ($valor === null || !is_numeric($valor) || (int) $valor < 1 || (int) $valor > 10) {
                $errores[] = "Debe responder '{$pregunta['texto']}' con un valor entre 1 y 10.";
            }
        }

        if (!empty($errores)) {
            Sesion::guardar('flash_encuesta', [
                'tipo' => 'error',
                'mensaje' => implode(' ', $errores),
            ]);
            redirigir('/dashboard/encuestas?plantilla=' . urlencode($plantillaId));
            return;
        }

        // (Acá se persistiría en la base de datos)
        Sesion::guardar('flash_encuesta', [
            'tipo' => 'success',
            'mensaje' => "Encuesta \"{$plantilla['nombre']}\" enviada correctamente. ¡Gracias!",
        ]);

        redirigir('/dashboard/encuestas?plantilla=' . urlencode($plantillaId));
    }

    // ==========================================
    // MÓDULO PERMISOS (matriz ESRE)
    // ==========================================

    /**
     * Muestra la matriz de permisos: roles × recursos × acciones.
     * Solo accesible para administradores.
     */
    public function permisos(): void
    {
        if (!Roles::permiso($this->rol, 'permisos', 'ver')) {
            abortar(403);
        }

        vista('modulos/permisos/inicio', [
            'titulo_pagina' => 'Matriz de Permisos',
            'nombre' => $this->nombre_usuario,
            'rol' => $this->rol,
            'matriz' => Roles::matriz(),
            'recursos' => Roles::recursos(),
            'acciones' => Roles::acciones(),
            'roles' => Roles::labels(),
        ], 'admin');
    }

    // ==========================================
    // MÓDULO USUARIOS (baja lógica)
    // ==========================================

    /**
     * Lista los usuarios del sistema con su estado (activo/inactivo).
     */
    public function usuarios(): void
    {
        if (!Roles::permiso($this->rol, 'usuarios', 'ver')) {
            abortar(403);
        }

        $flash = Sesion::obtener('flash_usuario');
        Sesion::eliminar('flash_usuario');

        vista('modulos/usuarios/inicio', [
            'titulo_pagina' => 'Gestión de Usuarios',
            'nombre' => $this->nombre_usuario,
            'rol' => $this->rol,
            'usuarios' => Usuarios::todos(),
            'roles' => Roles::labels(),
            'flash' => $flash,
        ], 'admin');
    }

    /**
     * Da de baja a un usuario (soft delete: setea fecha_baja).
     * NUNCA elimina al usuario del array.
     */
    public function usuarioBaja(string $username): void
    {
        if (!Roles::permiso($this->rol, 'usuarios', 'editar')) {
            abortar(403);
        }

        $resultado = Usuarios::darBaja($username);

        Sesion::guardar('flash_usuario', [
            'tipo' => $resultado['success'] ? 'success' : 'error',
            'mensaje' => $resultado['message'],
        ]);

        redirigir('/dashboard/usuarios');
    }

    /**
     * Reactiva un usuario (limpia fecha_baja → null).
     */
    public function usuarioReactivar(string $username): void
    {
        if (!Roles::permiso($this->rol, 'usuarios', 'editar')) {
            abortar(403);
        }

        $resultado = Usuarios::reactivar($username);

        Sesion::guardar('flash_usuario', [
            'tipo' => $resultado['success'] ? 'success' : 'error',
            'mensaje' => $resultado['message'],
        ]);

        redirigir('/dashboard/usuarios');
    }
}
