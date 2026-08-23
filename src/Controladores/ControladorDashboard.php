<?php

namespace Controladores;

use Nucleo\Sesion;
use Nucleo\RutaProtegida;
use Nucleo\Constantes\Roles;
use Nucleo\Constantes\PlantillasEncuestas;
use Modelos\ModeloTraslado;
use Modelos\ModeloUsuario;
use Modelos\ModeloVehiculo;

class ControladorDashboard extends RutaProtegida
{
    private string $nombre_usuario;
    private string $rol;
    private ModeloTraslado $modelo_traslado;
    private ModeloUsuario $modelo_usuario;
    private ModeloVehiculo $modelo_vehiculo;

    public function __construct()
    {
        parent::__construct();

        $usuario = Sesion::obtener('user');
        $this->modelo_traslado = new ModeloTraslado();
        $this->modelo_usuario = new ModeloUsuario();
        $this->modelo_vehiculo = new ModeloVehiculo();
        $this->nombre_usuario = $usuario['nombre'];
        $this->rol = $usuario['rol'];
    }

    /**
     * Muestra el modulo documentos
     */
    public function documentos(): void
    {
        $modeloDoc = new \Modelos\ModeloDocumento();
        
        // Capturamos el mensaje flash si es que acabamos de crear un documento
        $flash = Sesion::obtener('flash_documento');
        Sesion::eliminar('flash_documento');

        vista('modulos/documentos/inicio', [
            'titulo_pagina' => "Gestion de Documentos",
            'nombre' => $this->nombre_usuario,
            'rol' => $this->rol,
            'documentos' => $modeloDoc->obtenerTodos(),
            'categorias' => $modeloDoc->obtenerCategorias(),
            'flash' => $flash // Pasamos el mensaje a la vista
        ], 'admin');
    }

    /**
     * Muestra los PDFs de una categoría específica.
     * Esta vista es la que abre el QR desde el celular.
     */
    public function documentosCategoria(string $slug): void
    {
        $modeloDoc = new \Modelos\ModeloDocumento();
        $documentos = $modeloDoc->obtenerPorSlugCategoria($slug);

        // Buscar el nombre legible de la categoría (lo sacamos del primer documento si existe)
        $nombreCategoria = 'Categoría desconocida';
        if (!empty($documentos)) {
            $nombreCategoria = $documentos[0]['nombre_categoria'] ?? 'Categoría desconocida';
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
     * Procesa la creación de un nuevo documento (Issue # 108).
     */
    public function crearDocumento(): void
    {
        // 1. Verificar permisos (Control de acceso)
        if (!Roles::permiso($this->rol, 'documentos', 'crear')) {
            abortar(403);
        }

        // 2. Validar seguridad (CSRF)
        $csrf = $_POST['_csrf'] ?? '';
        if (!Sesion::validarTokenCsrf($csrf)) {
            Sesion::guardar('flash_documento', [
                'tipo' => 'error',
                'mensaje' => 'Error de seguridad. Por favor, intenta de nuevo.'
            ]);
            redirigir('/dashboard/documentos');
            return;
        }

        // 3. Capturar los datos enviados por el formulario
        $titulo = trim($_POST['titulo'] ?? '');
        $idCategoria = $_POST['id_categoria'] ?? null;
        
        // (El Issue # 116 se encargará de subir el archivo real. Por ahora dejamos una ruta temporal)
        $rutaArchivo = 'assets/uploads/temporal.pdf';

        // 4. Capturar la CI del funcionario desde la sesión
        $usuario = Sesion::obtener('user');
        $ciFuncionario = $usuario['ci'] ?? null;

        // Validar que los datos obligatorios no estén vacíos
        if (empty($titulo) || empty($idCategoria) || empty($ciFuncionario)) {
            Sesion::guardar('flash_documento', [
                'tipo' => 'error',
                'mensaje' => 'Faltan completar campos obligatorios.'
            ]);
            redirigir('/dashboard/documentos');
            return;
        }

        // 5. Instanciar el modelo y guardar en la BD
        $modeloDoc = new \Modelos\ModeloDocumento();
        $exito = $modeloDoc->crear([
            'id_categoria'     => (int)$idCategoria,
            'titulo'           => $titulo,
            'ruta_archivo'     => $rutaArchivo,
            'documento_activo' => 1,
            'ci_funcionario'   => $ciFuncionario
        ]);

        // 6. Feedback visual para el usuario (Flash) y redirección
        if ($exito) {
            Sesion::guardar('flash_documento', [
                'tipo' => 'success',
                'mensaje' => '¡El documento fue registrado exitosamente!'
            ]);
        } else {
            Sesion::guardar('flash_documento', [
                'tipo' => 'error',
                'mensaje' => 'Ocurrió un error al intentar guardar en la base de datos.'
            ]);
        }

        redirigir('/dashboard/documentos');
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
    // MÓDULO PERMISOS (API)
    // ==========================================

    /**
     * Alterna una celda de la matriz de permisos (#130).
     *
     * Body esperado:
     *   - id_rol    int
     *   - recurso   string
     *   - accion    string (ver | crear | editar | eliminar)
     *   - permitido bool
     *
     * Devuelve `{success: true, antes: bool, despues: bool}` o
     * `{success: false, message: string}` con código 4xx/5xx.
     */
    public function apiPermisoToggle(): void
    {
        header('Content-Type: application/json');

        if (!Roles::permiso($this->rol, 'permisos', 'editar')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tenés permisos para editar la matriz.',
            ]);
            return;
        }

        $data = $this->leerBodyConCsrf();
        if ($data === null) return;

        $idRol    = (int)($data['id_rol'] ?? 0);
        $recurso  = trim((string)($data['recurso'] ?? ''));
        $accion   = trim((string)($data['accion'] ?? ''));
        $permitido = filter_var(
            $data['permitido'] ?? null,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );

        if ($idRol <= 0 || $recurso === '' || $accion === '' || $permitido === null) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Datos incompletos o inválidos.',
            ]);
            return;
        }

        $user = Sesion::obtener('user');
        $usuarioId = is_array($user) ? (int)($user['id'] ?? 0) : 0;
        if ($usuarioId <= 0) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Sesión inválida.',
            ]);
            return;
        }

        try {
            $modelo = new \Modelos\ModeloPermiso();
            $diff = $modelo->alternar($idRol, $recurso, $accion, $permitido, $usuarioId);
            echo json_encode([
                'success' => true,
                'antes'   => $diff['antes'],
                'despues' => $diff['despues'],
            ]);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('apiPermisoToggle: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error interno al alternar el permiso.',
            ]);
        }
    }

    /**
     * Alterna varias celdas en una sola transacción (#130).
     *
     * Body esperado:
     *   - toggles: array<{id_rol:int, recurso:string, accion:string, permitido:bool}>
     */
    public function apiPermisoBatch(): void
    {
        header('Content-Type: application/json');

        if (!Roles::permiso($this->rol, 'permisos', 'editar')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tenés permisos para editar la matriz.',
            ]);
            return;
        }

        $data = $this->leerBodyConCsrf();
        if ($data === null) return;

        $toggles = $data['toggles'] ?? null;
        if (!is_array($toggles) || empty($toggles)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Falta el array de toggles.',
            ]);
            return;
        }

        $user = Sesion::obtener('user');
        $usuarioId = is_array($user) ? (int)($user['id'] ?? 0) : 0;
        if ($usuarioId <= 0) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Sesión inválida.',
            ]);
            return;
        }

        try {
            $modelo = new \Modelos\ModeloPermiso();
            $diffs = $modelo->alternarBatch($toggles, $usuarioId);
            echo json_encode(['success' => true, 'diffs' => $diffs]);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('apiPermisoBatch: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error interno al alternar los permisos.',
            ]);
        }
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
     * Procesa el envío de una encuesta
     */
    public function encuestaSubmit() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            \Nucleo\Sesion::guardar('flash_encuesta', ['tipo' => 'error', 'mensaje' => 'Método no permitido']);
            header('Location: /dashboard/encuestas');
            exit;
        }

        $idEncuesta = (int)($_POST['id_encuesta'] ?? 1); 
        $modelo = new \Modelos\ModeloEncuesta();
        
        $encuestaBD = $modelo->obtenerPorId($idEncuesta);
        $esAnonima = $encuestaBD ? (bool)$encuestaBD['es_anonima'] : false;

        $usuario = \Nucleo\Sesion::obtener('user');
        $ciUsuario = $esAnonima ? null : ($usuario['ci'] ?? null);
        $idUsuario = $usuario['id'] ?? null; // Capturamos el ID para la auditoría

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
            'id_encuesta' => $idEncuesta,
            'ci_usuario' => $ciUsuario,
            'calificacion' => $calificacionGeneral,
            'respuestas_detalle' => $respuestasDetalle
        ];
        
        if ($modelo->guardarRespuestas($data)) {
            // Auditoría manual con IP y Detalles
            $modeloAuditoria = new \Modelos\ModeloAuditoria();
            $detalleAuditoria = $esAnonima ? 'Encuesta interna respondida de forma anónima' : 'Encuesta respondida por CI: ' . $ciUsuario;
            $modeloAuditoria->registrar('CREAR', 'respuestas_encuesta', $detalleAuditoria, $idUsuario);

            \Nucleo\Sesion::guardar('flash_encuesta', ['tipo' => 'exito', 'mensaje' => 'Encuesta guardada correctamente.']);
        } else {
            \Nucleo\Sesion::guardar('flash_encuesta', ['tipo' => 'error', 'mensaje' => 'Error al guardar.']);
        }
        
        header('Location: /dashboard/encuestas');
        exit;
        
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

        // Resolvemos id_rol (PK numérica de la BD) por cada UI key del
        // catálogo. La API espera id_rol numérico, no la UI key — y
        // del lado JS no queremos ir a buscarlo en cada click (#130).
        $idRoles = [];
        try {
            $modelo = new \Modelos\ModeloPermiso();
            $rolesBd = $modelo->obtenerRoles(); // [id_rol] => tipo_rol enum
            foreach (Roles::labels() as $uiKey => $label) {
                $enum = Roles::mapUiToEnum($uiKey);
                if ($enum === null) continue;
                $id = array_search($enum, $rolesBd, true);
                if ($id !== false) {
                    $idRoles[$uiKey] = (int)$id;
                }
            }
        } catch (\Throwable $e) {
            error_log('permisos(): no se pudo resolver idRoles: ' . $e->getMessage());
            $idRoles = [];
        }

        vista('modulos/permisos/inicio', [
            'titulo_pagina' => 'Matriz de Permisos',
            'nombre' => $this->nombre_usuario,
            'rol' => $this->rol,
            'matriz' => Roles::matriz(),
            'recursos' => Roles::recursos(),
            'acciones' => Roles::acciones(),
            'roles' => Roles::labels(),
            'id_roles' => $idRoles,
            'puede_editar' => Roles::permiso($this->rol, 'permisos', 'editar'),
            'csrf_token' => \Nucleo\Sesion::generarTokenCsrf(),
        ], 'admin');
    }

    // ==========================================
    // MÓDULO USUARIOS (CRUD contra BD)
    // ==========================================

    /**
     * Lista los usuarios del sistema con filtros server-side.
     *
     * Query params reconocidos:
     *   - estado  : 'activos' | 'inactivos' | 'todos' (default 'todos')
     *   - rol     : UI key del catálogo (vacío = todos)
     *   - q       : texto libre (CI / nombre / apellido / email)
     *   - pagina  : número de página (default 1)
     */
    public function usuarios(): void
    {
        if (!Roles::permiso($this->rol, 'usuarios', 'ver')) {
            abortar(403);
        }

        $estado = (string)($_GET['estado'] ?? 'todos');
        if (!in_array($estado, ['todos', 'activos', 'inactivos'], true)) {
            $estado = 'todos';
        }

        $rolFiltro = (string)($_GET['rol'] ?? '');
        if (!array_key_exists($rolFiltro, Roles::labels())) {
            $rolFiltro = '';
        }

        $q = (string)($_GET['q'] ?? '');
        $pagina = (int)($_GET['pagina'] ?? 1);
        if ($pagina < 1) {
            $pagina = 1;
        }
        $porPagina = 25;

        $usuarios = $this->modelo_usuario->listar($estado, $rolFiltro, $q, $pagina, $porPagina);
        $total = $this->modelo_usuario->contar($estado, $rolFiltro, $q);
        $totalPaginas = max(1, (int)ceil($total / $porPagina));
        $stats_estado = $this->modelo_usuario->contarPorEstado();
        $stats_roles = $this->modelo_usuario->contarPorRol();

        $flash = Sesion::obtener('flash_usuario');
        Sesion::eliminar('flash_usuario');

        vista('modulos/usuarios/inicio', [
            'titulo_pagina' => 'Gestión de Usuarios',
            'nombre' => $this->nombre_usuario,
            'rol' => $this->rol,
            'usuarios' => $usuarios,
            'roles' => Roles::labels(),
            'filtros' => [
                'estado' => $estado,
                'rol' => $rolFiltro,
                'q' => $q,
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'total' => $total,
                'total_paginas' => $totalPaginas,
            ],
            'stats_estado' => $stats_estado,
            'stats_roles' => $stats_roles,
            'puede_crear' => Roles::permiso($this->rol, 'usuarios', 'crear'),
            'puede_editar' => Roles::permiso($this->rol, 'usuarios', 'editar'),
            'flash' => $flash,
        ], 'admin');
    }

    /**
     * Muestra el formulario de alta de un usuario nuevo.
     */
    public function usuarioNuevo(): void
    {
        if (!Roles::permiso($this->rol, 'usuarios', 'crear')) {
            abortar(403);
        }

        $flash = Sesion::obtener('flash_usuario');
        Sesion::eliminar('flash_usuario');

        vista('modulos/usuarios/nuevo', [
            'titulo_pagina' => 'Nuevo Usuario',
            'nombre' => $this->nombre_usuario,
            'rol' => $this->rol,
            'roles' => Roles::labels(),
            'catalogo_roles' => $this->modelo_usuario->obtenerCatalogoRoles(),
            'flash' => $flash,
            'csrf' => Sesion::generarTokenCsrf(),
        ], 'admin');
    }

    /**
     * Procesa el alta de un usuario nuevo.
     */
    public function usuarioCrear(): void
    {
        if (!Roles::permiso($this->rol, 'usuarios', 'crear')) {
            abortar(403);
        }

        if (!Sesion::validarTokenCsrf((string)($_POST['csrf_token'] ?? ''))) {
            Sesion::guardar('flash_usuario', ['tipo' => 'error', 'mensaje' => 'Token inválido.']);
            redirigir('/dashboard/usuarios/nuevo');
            return;
        }

        $rolesPost = $_POST['roles'] ?? [];
        if (!is_array($rolesPost)) {
            $rolesPost = [];
        }
        $rolesUi = array_values(array_filter(array_map('strval', $rolesPost), 'strlen'));

        try {
            $id = $this->modelo_usuario->crear([
                'ci'         => (int)($_POST['ci'] ?? 0),
                'nombre'     => (string)($_POST['nombre'] ?? ''),
                'apellido'   => (string)($_POST['apellido'] ?? ''),
                'email'      => (string)($_POST['email'] ?? ''),
                'contrasena' => (string)($_POST['contrasena'] ?? ''),
                'roles'      => $rolesUi,
            ]);
            Sesion::guardar('flash_usuario', [
                'tipo' => 'success',
                'mensaje' => "Usuario creado correctamente (id {$id}).",
            ]);
            redirigir('/dashboard/usuarios');
        } catch (\InvalidArgumentException $e) {
            Sesion::guardar('flash_usuario', ['tipo' => 'error', 'mensaje' => $e->getMessage()]);
            redirigir('/dashboard/usuarios/nuevo');
        } catch (\Throwable $e) {
            error_log('usuarioCrear: ' . $e->getMessage());
            Sesion::guardar('flash_usuario', [
                'tipo' => 'error',
                'mensaje' => 'Error interno al crear el usuario.',
            ]);
            redirigir('/dashboard/usuarios/nuevo');
        }
    }

    /**
     * Muestra el formulario de edición de un usuario.
     * El parámetro de URL es el id (PK de la tabla).
     */
    public function usuarioEditar(int $id): void
    {
        if (!Roles::permiso($this->rol, 'usuarios', 'editar')) {
            abortar(403);
        }

        $usuario = $this->modelo_usuario->buscarPorId($id);
        if (!$usuario) {
            abortar(404);
        }

        $flash = Sesion::obtener('flash_usuario');
        Sesion::eliminar('flash_usuario');

        vista('modulos/usuarios/editar', [
            'titulo_pagina' => 'Editar Usuario',
            'nombre' => $this->nombre_usuario,
            'rol' => $this->rol,
            'usuario' => $usuario,
            'roles' => Roles::labels(),
            'catalogo_roles' => $this->modelo_usuario->obtenerCatalogoRoles(),
            'flash' => $flash,
            'csrf' => Sesion::generarTokenCsrf(),
        ], 'admin');
    }

    /**
     * Procesa la edición de un usuario (nombre / apellido / email / roles).
     * CI es inmutable: si viene en el POST se ignora silenciosamente.
     */
    public function usuarioActualizar(int $id): void
    {
        if (!Roles::permiso($this->rol, 'usuarios', 'editar')) {
            abortar(403);
        }

        if (!Sesion::validarTokenCsrf((string)($_POST['csrf_token'] ?? ''))) {
            Sesion::guardar('flash_usuario', ['tipo' => 'error', 'mensaje' => 'Token inválido.']);
            redirigir('/dashboard/usuarios/' . $id . '/editar');
            return;
        }

        $rolesPost = $_POST['roles'] ?? [];
        if (!is_array($rolesPost)) {
            $rolesPost = [];
        }
        $rolesUi = array_values(array_filter(array_map('strval', $rolesPost), 'strlen'));

        try {
            $payload = [
                'nombre'   => (string)($_POST['nombre'] ?? ''),
                'apellido' => (string)($_POST['apellido'] ?? ''),
                'email'    => (string)($_POST['email'] ?? ''),
                'roles'    => $rolesUi,
            ];
            // La contraseña es opcional en edición; si viene vacía no se toca.
            $contrasena = (string)($_POST['contrasena'] ?? '');
            if ($contrasena !== '') {
                $payload['contrasena'] = $contrasena;
            }

            $this->modelo_usuario->actualizar($id, $payload);

            Sesion::guardar('flash_usuario', [
                'tipo' => 'success',
                'mensaje' => "Usuario actualizado correctamente.",
            ]);
            redirigir('/dashboard/usuarios');
        } catch (\InvalidArgumentException $e) {
            Sesion::guardar('flash_usuario', ['tipo' => 'error', 'mensaje' => $e->getMessage()]);
            redirigir('/dashboard/usuarios/' . $id . '/editar');
        } catch (\Throwable $e) {
            error_log('usuarioActualizar: ' . $e->getMessage());
            Sesion::guardar('flash_usuario', [
                'tipo' => 'error',
                'mensaje' => 'Error interno al actualizar el usuario.',
            ]);
            redirigir('/dashboard/usuarios/' . $id . '/editar');
        }
    }

    /**
     * Da de baja a un usuario (soft delete: activo = FALSE).
     * El parámetro es el id del usuario (PK de la tabla).
     */
    public function usuarioBaja(int $id): void
    {
        if (!Roles::permiso($this->rol, 'usuarios', 'editar')) {
            abortar(403);
        }

        try {
            $this->modelo_usuario->desactivar($id);
            Sesion::guardar('flash_usuario', [
                'tipo' => 'success',
                'mensaje' => 'Usuario dado de baja correctamente.',
            ]);
        } catch (\InvalidArgumentException $e) {
            Sesion::guardar('flash_usuario', ['tipo' => 'error', 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('usuarioBaja: ' . $e->getMessage());
            Sesion::guardar('flash_usuario', [
                'tipo' => 'error',
                'mensaje' => 'Error interno al dar de baja.',
            ]);
        }

        redirigir('/dashboard/usuarios');
    }

    /**
     * Reactiva un usuario (activo = TRUE).
     */
    public function usuarioReactivar(int $id): void
    {
        if (!Roles::permiso($this->rol, 'usuarios', 'editar')) {
            abortar(403);
        }

        try {
            $this->modelo_usuario->reactivar($id);
            Sesion::guardar('flash_usuario', [
                'tipo' => 'success',
                'mensaje' => 'Usuario reactivado correctamente.',
            ]);
        } catch (\InvalidArgumentException $e) {
            Sesion::guardar('flash_usuario', ['tipo' => 'error', 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('usuarioReactivar: ' . $e->getMessage());
            Sesion::guardar('flash_usuario', [
                'tipo' => 'error',
                'mensaje' => 'Error interno al reactivar.',
            ]);
        }

        redirigir('/dashboard/usuarios');
    }

    // ==========================================
    // MÓDULO VEHÍCULOS (CRUD + liberación auto)
    // Issue #131 — Espejo de la gestión de usuarios.
    // ==========================================

    /**
     * Listado de vehículos con filtros, paginación y resumen por estado.
     */
    public function vehiculos(): void
    {
        if (!Roles::permiso($this->rol, 'vehiculos', 'ver')) {
            abortar(403);
        }

        $estado = (string)($_GET['estado'] ?? 'todos');
        if (!in_array($estado, ['todos', 'disponibles', 'no_disponibles'], true)) {
            $estado = 'todos';
        }

        $activo = (string)($_GET['activo'] ?? 'todos');
        if (!in_array($activo, ['todos', 'activos', 'inactivos'], true)) {
            $activo = 'todos';
        }

        $tipo = (int)($_GET['tipo'] ?? 0);
        $q = (string)($_GET['q'] ?? '');
        $pagina = (int)($_GET['pagina'] ?? 1);
        if ($pagina < 1) {
            $pagina = 1;
        }
        $porPagina = 25;

        $vehiculos = $this->modelo_vehiculo->listar($estado, $tipo, $activo, $q, $pagina, $porPagina);
        $total = $this->modelo_vehiculo->contar($estado, $tipo, $activo, $q);
        $totalPaginas = max(1, (int)ceil($total / $porPagina));
        $stats_estado = $this->modelo_vehiculo->contarPorEstado();
        $tipos = $this->modelo_vehiculo->obtenerTiposVehiculo();

        $flash = Sesion::obtener('flash_vehiculo');
        Sesion::eliminar('flash_vehiculo');

        vista('modulos/vehiculos/inicio', [
            'titulo_pagina' => 'Gestión de Vehículos',
            'nombre' => $this->nombre_usuario,
            'rol' => $this->rol,
            'vehiculos' => $vehiculos,
            'tipos' => $tipos,
            'filtros' => [
                'estado' => $estado,
                'activo' => $activo,
                'tipo' => $tipo,
                'q' => $q,
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'total' => $total,
                'total_paginas' => $totalPaginas,
            ],
            'stats_estado' => $stats_estado,
            'puede_crear' => Roles::permiso($this->rol, 'vehiculos', 'crear'),
            'puede_editar' => Roles::permiso($this->rol, 'vehiculos', 'editar'),
            'puede_eliminar' => Roles::permiso($this->rol, 'vehiculos', 'eliminar'),
            'flash' => $flash,
        ], 'admin');
    }

    /**
     * Muestra el formulario de alta de un vehículo.
     */
    public function vehiculoNuevo(): void
    {
        if (!Roles::permiso($this->rol, 'vehiculos', 'crear')) {
            abortar(403);
        }

        $flash = Sesion::obtener('flash_vehiculo');
        Sesion::eliminar('flash_vehiculo');

        vista('modulos/vehiculos/nuevo', [
            'titulo_pagina' => 'Nuevo Vehículo',
            'nombre' => $this->nombre_usuario,
            'rol' => $this->rol,
            'tipos' => $this->modelo_vehiculo->obtenerTiposVehiculo(),
            'csrf' => Sesion::generarTokenCsrf(),
            'flash' => $flash,
        ], 'admin');
    }

    /**
     * POST: crea un vehículo nuevo.
     */
    public function vehiculoCrear(): void
    {
        if (!Roles::permiso($this->rol, 'vehiculos', 'crear')) {
            abortar(403);
        }

        if (!Sesion::validarTokenCsrf((string)($_POST['csrf_token'] ?? ''))) {
            Sesion::guardar('flash_vehiculo', [
                'tipo' => 'error',
                'mensaje' => 'Token inválido.',
            ]);
            redirigir('/dashboard/vehiculos/nuevo');
            return;
        }

        $matricula = (string)($_POST['matricula'] ?? '');
        $idTipo = (int)($_POST['id_tipo_vehiculo'] ?? 0);

        try {
            $nuevoId = $this->modelo_vehiculo->crear($matricula, $idTipo);
            Sesion::guardar('flash_vehiculo', [
                'tipo' => 'success',
                'mensaje' => "Vehículo creado correctamente (matrícula {$matricula}, id {$nuevoId}).",
            ]);
            redirigir('/dashboard/vehiculos');
        } catch (\InvalidArgumentException $e) {
            Sesion::guardar('flash_vehiculo', [
                'tipo' => 'error',
                'mensaje' => $e->getMessage(),
            ]);
            redirigir('/dashboard/vehiculos/nuevo');
        } catch (\Throwable $e) {
            error_log('vehiculoCrear: ' . $e->getMessage());
            Sesion::guardar('flash_vehiculo', [
                'tipo' => 'error',
                'mensaje' => 'Error interno al crear el vehículo.',
            ]);
            redirigir('/dashboard/vehiculos/nuevo');
        }
    }

    /**
     * Muestra el formulario de edición de un vehículo existente.
     */
    public function vehiculoEditar(int $id): void
    {
        if (!Roles::permiso($this->rol, 'vehiculos', 'editar')) {
            abortar(403);
        }

        $vehiculo = $this->modelo_vehiculo->buscarPorId($id);
        if (!$vehiculo) {
            abortar(404);
        }

        $flash = Sesion::obtener('flash_vehiculo');
        Sesion::eliminar('flash_vehiculo');

        vista('modulos/vehiculos/editar', [
            'titulo_pagina' => 'Editar Vehículo #' . $id,
            'nombre' => $this->nombre_usuario,
            'rol' => $this->rol,
            'vehiculo' => $vehiculo,
            'tipos' => $this->modelo_vehiculo->obtenerTiposVehiculo(),
            'csrf' => Sesion::generarTokenCsrf(),
            'flash' => $flash,
        ], 'admin');
    }

    /**
     * POST: actualiza matricula y tipo de un vehículo.
     */
    public function vehiculoActualizar(int $id): void
    {
        if (!Roles::permiso($this->rol, 'vehiculos', 'editar')) {
            abortar(403);
        }

        if (!Sesion::validarTokenCsrf((string)($_POST['csrf_token'] ?? ''))) {
            Sesion::guardar('flash_vehiculo', [
                'tipo' => 'error',
                'mensaje' => 'Token inválido.',
            ]);
            redirigir("/dashboard/vehiculos/{$id}/editar");
            return;
        }

        $matricula = (string)($_POST['matricula'] ?? '');
        $idTipo = (int)($_POST['id_tipo_vehiculo'] ?? 0);

        try {
            $this->modelo_vehiculo->actualizar($id, $matricula, $idTipo);
            Sesion::guardar('flash_vehiculo', [
                'tipo' => 'success',
                'mensaje' => 'Vehículo actualizado correctamente.',
            ]);
            redirigir('/dashboard/vehiculos');
        } catch (\InvalidArgumentException $e) {
            Sesion::guardar('flash_vehiculo', [
                'tipo' => 'error',
                'mensaje' => $e->getMessage(),
            ]);
            redirigir("/dashboard/vehiculos/{$id}/editar");
        } catch (\Throwable $e) {
            error_log('vehiculoActualizar: ' . $e->getMessage());
            Sesion::guardar('flash_vehiculo', [
                'tipo' => 'error',
                'mensaje' => 'Error interno al actualizar el vehículo.',
            ]);
            redirigir("/dashboard/vehiculos/{$id}/editar");
        }
    }

    /**
     * POST: soft delete del vehículo (`activo = FALSE`).
     */
    public function vehiculoBaja(int $id): void
    {
        if (!Roles::permiso($this->rol, 'vehiculos', 'eliminar')) {
            abortar(403);
        }

        try {
            $this->modelo_vehiculo->desactivar($id);
            Sesion::guardar('flash_vehiculo', [
                'tipo' => 'success',
                'mensaje' => 'Vehículo dado de baja correctamente.',
            ]);
        } catch (\InvalidArgumentException $e) {
            Sesion::guardar('flash_vehiculo', [
                'tipo' => 'error',
                'mensaje' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            error_log('vehiculoBaja: ' . $e->getMessage());
            Sesion::guardar('flash_vehiculo', [
                'tipo' => 'error',
                'mensaje' => 'Error interno al dar de baja el vehículo.',
            ]);
        }

        redirigir('/dashboard/vehiculos');
    }

    /**
     * POST: reactiva un vehículo (`activo = TRUE`). NO cambia `estado`.
     */
    public function vehiculoReactivar(int $id): void
    {
        if (!Roles::permiso($this->rol, 'vehiculos', 'editar')) {
            abortar(403);
        }

        try {
            $this->modelo_vehiculo->reactivar($id);
            Sesion::guardar('flash_vehiculo', [
                'tipo' => 'success',
                'mensaje' => 'Vehículo reactivado correctamente.',
            ]);
        } catch (\InvalidArgumentException $e) {
            Sesion::guardar('flash_vehiculo', [
                'tipo' => 'error',
                'mensaje' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            error_log('vehiculoReactivar: ' . $e->getMessage());
            Sesion::guardar('flash_vehiculo', [
                'tipo' => 'error',
                'mensaje' => 'Error interno al reactivar.',
            ]);
        }

        redirigir('/dashboard/vehiculos');
    }
}
