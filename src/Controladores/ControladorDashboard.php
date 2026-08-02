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
        $trasladosRaw = $this->modelo_traslado->obtenerTodosActivos();

        // Transformar datos al formato que espera la vista
        $traslados = array_map(function ($t) {
            $primerDestino = $t['destinos'][0]['nombre'] ?? 'Sin destino';

            $estadoMap = [
                'solicitado' => 'Solicitado',
                'en_proceso' => 'En Proceso',
                'completado' => 'Finalizado',
                'cancelado' => 'Cancelado',
            ];

            $tipoMap = [
                'paciente_alta' => 'Paciente',
                'biologico' => 'Biológico',
                'equipamiento' => 'Equipamiento',
                'doctor' => 'Doctor',
            ];

            $prioridadMap = [
                'rojo' => 'Rojo',
                'amarillo' => 'Amarillo',
                'verde' => 'Verde',
            ];

            return [
                'id' => (string) $t['id'],
                'tipo' => $tipoMap[$t['tipo']] ?? $t['tipo'],
                'ubicacion_origen' => $t['origen'],
                'ubicacion_destino' => $primerDestino,
                'fecha_realizacion' => 'Hace ' . rand(1, 7) . ' dias',
                'chofer' => $t['conductor'],
                'estado' => $estadoMap[$t['estado']] ?? $t['estado'],
                'estado_interno' => $t['estado'],
                'prioridad' => $prioridadMap[$t['prioridad']] ?? 'Sin prioridad',
                'prioridad_interna' => $t['prioridad'] ?? 'verde',
            ];
        }, $trasladosRaw);

        vista('modulos/traslados/inicio', [
            'titulo_pagina' => "Trazabilidad de Traslados",
            'nombre' => $this->nombre_usuario,
            'rol' => $this->rol,
            'traslados' => $traslados,
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
            'ubicaciones'   => $ubicaciones
        ], 'admin');
    }

    public function detalleTraslado(int $id): void
    {

        $id = (int)($params['id'] ?? 0);
        $traslado = $id > 0 ? $this->modelo_traslado->obtenerPorId($id) : null;

        vista('modulos/traslados/detalle', [
            'titulo_pagina' => 'Detalle del Traslado',
            'nombre' => $this->nombre_usuario,
            'rol' => $this->rol,
            'traslado'      => $traslado        // <-- Inyectamos el traslado específico
        ], 'admin');
    }

    // ==========================================
    // API METHODS
    // ==========================================
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

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['destino_orden']) || !isset($data['timestamp'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            return;
        }

        $resultado = $this->modelo_traslado->registrarArribo(
            $id,
            $data['destino_orden'],
            $data['timestamp']
        );

        if ($resultado['success']) {
            $this->modelo_traslado->avanzarPaso($id);
        }

        echo json_encode($resultado);
    }

    public function apiCrearReporte(int $id): void
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['destino_orden']) || !isset($data['tipo_problema']) || !isset($data['mensaje'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            return;
        }

        $resultado = $this->modelo_traslado->crearReporte(
            $id,
            $data['destino_orden'],
            $data['tipo_problema'],
            $data['mensaje']
        );

        echo json_encode($resultado);
    }

    public function apiCancelarTraslado(int $id): void
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['destino_orden']) || !isset($data['tipo_problema']) || !isset($data['mensaje'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            return;
        }

        $resultado = $this->modelo_traslado->cancelar(
            $id,
            $data['destino_orden'],
            $data['tipo_problema'],
            $data['mensaje']
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
