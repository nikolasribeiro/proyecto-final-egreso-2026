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
