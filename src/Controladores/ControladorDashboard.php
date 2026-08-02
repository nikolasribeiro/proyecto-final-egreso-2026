<?php

namespace Controladores;

use Nucleo\Sesion;
use Nucleo\RutaProtegida;
use Nucleo\Constantes\Roles;
use Nucleo\Constantes\Usuarios;
use Nucleo\Constantes\PlantillasEncuestas;
use Modelos\ModeloDocumento;
use Modelos\ModeloTraslado;

class ControladorDashboard extends RutaProtegida
{
    private string $nombre_usuario;
    private string $rol;
    private ModeloDocumento $modelo_documento;
    private ModeloTraslado $modelo_traslado;

    public function __construct()
    {
        parent::__construct();

        $usuario = Sesion::obtener('user');
        $this->modelo_documento = new ModeloDocumento();
        $this->modelo_traslado = new ModeloTraslado();
        $this->nombre_usuario = $usuario['nombre'];
        $this->rol = $usuario['rol'];
    }

    /**
     * Muestra el modulo documentos con categorías y documentos persistidos.
     */
    public function documentos(): void
    {
        $flash = Sesion::obtener('flash_documentos');
        Sesion::eliminar('flash_documentos');

        vista('modulos/documentos/inicio', [
            'titulo_pagina' => 'Gestion de Documentos',
            'nombre' => $this->nombre_usuario,
            'rol' => $this->rol,
            'documentos' => $this->modelo_documento->obtenerTodos(),
            'categorias' => $this->modelo_documento->obtenerCategorias(),
            'flash' => $flash,
            'csrf_token' => Sesion::generarTokenCsrf(),
            'puede_crear_documentos' => Roles::permiso($this->rol, 'documentos', 'crear'),
        ], 'admin');
    }

    /**
     * Muestra los PDFs de una categoría específica.
     * Esta vista es la que abre el QR desde el celular.
     */
    public function documentosCategoria(string $slug): void
    {
        $documentos = $this->modelo_documento->obtenerPorCategoria($slug);
        $nombreCategoria = $this->modelo_documento->obtenerNombreCategoriaPorSlug($slug)
            ?? 'Categoría desconocida';

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
     * Guarda un documento cargado desde el modal.
     */
    public function subirDocumento(): void
    {
        if (!Roles::permiso($this->rol, 'documentos', 'crear')) {
            abortar(403);
        }

        $errores = [];
        $usuario = Sesion::obtener('user', []);
        $token = $_POST['csrf_token'] ?? '';
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $idCategoria = filter_var($_POST['id_categoria'] ?? null, FILTER_VALIDATE_INT);
        $archivo = $_FILES['archivo'] ?? null;

        if (!Sesion::validarTokenCsrf($token)) {
            $errores[] = 'La sesión del formulario expiró. Recargá la página e intentá nuevamente.';
        }

        if ($titulo === '' || mb_strlen($titulo) > 200) {
            $errores[] = 'El título es obligatorio y no puede superar los 200 caracteres.';
        }

        if (!$idCategoria || !$this->modelo_documento->obtenerCategoriaPorId((int) $idCategoria)) {
            $errores[] = 'Seleccioná una categoría válida.';
        }

        if (!is_array($archivo) || !isset($archivo['error'], $archivo['tmp_name'])) {
            $errores[] = 'Seleccioná un archivo para cargar.';
        } elseif ($archivo['error'] !== UPLOAD_ERR_OK) {
            $errores[] = $this->mensajeErrorCarga((int) $archivo['error']);
        } else {
            $errores = array_merge($errores, $this->validarArchivo($archivo));
        }

        $ciFuncionario = $this->obtenerCiFuncionario($usuario);
        if ($ciFuncionario === null) {
            $errores[] = 'No se pudo identificar al usuario que realiza la carga.';
        }

        if (!empty($errores)) {
            Sesion::guardar('flash_documentos', [
                'tipo' => 'error',
                'mensaje' => implode(' ', $errores),
            ]);
            redirigir('/dashboard/documentos');
        }

        $extension = strtolower(pathinfo((string) $archivo['name'], PATHINFO_EXTENSION));
        $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $extension;
        $directorioUploads = dirname(__DIR__) . '/uploads';
        $rutaFisica = $directorioUploads . '/' . $nombreArchivo;
        $rutaPublica = '/uploads/' . $nombreArchivo;

        if (!is_dir($directorioUploads) && !mkdir($directorioUploads, 0750, true) && !is_dir($directorioUploads)) {
            $this->guardarErrorDocumento('No se pudo preparar el almacenamiento de archivos.');
        }

        if (!move_uploaded_file($archivo['tmp_name'], $rutaFisica)) {
            $this->guardarErrorDocumento('No se pudo guardar el archivo cargado.');
        }

        try {
            $this->modelo_documento->crear([
                'id_categoria' => (int) $idCategoria,
                'titulo' => $titulo,
                'ruta_archivo' => $rutaPublica,
                'ci_funcionario' => $ciFuncionario,
            ]);
        } catch (\Throwable $e) {
            if (is_file($rutaFisica)) {
                unlink($rutaFisica);
            }
            error_log('Error al registrar documento: ' . $e->getMessage());
            $this->guardarErrorDocumento('No se pudo registrar el documento en la base de datos.');
        }

        Sesion::guardar('flash_documentos', [
            'tipo' => 'success',
            'mensaje' => 'Documento cargado correctamente.',
        ]);
        redirigir('/dashboard/documentos');
    }

    /**
     * Crea una categoría desde el botón + del modal.
     */
    public function apiCrearCategoria(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!Roles::permiso($this->rol, 'documentos', 'crear')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tenés permisos para crear categorías.']);
            return;
        }

        $datos = json_decode(file_get_contents('php://input'), true);
        $datos = is_array($datos) ? $datos : $_POST;
        $token = (string) ($datos['csrf_token'] ?? '');
        $nombre = trim((string) ($datos['nombre_categoria'] ?? ''));

        if (!Sesion::validarTokenCsrf($token)) {
            http_response_code(419);
            echo json_encode(['success' => false, 'message' => 'La sesión expiró. Recargá la página e intentá nuevamente.']);
            return;
        }

        if ($nombre === '' || mb_strlen($nombre) > 100) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'La categoría debe tener entre 1 y 100 caracteres.']);
            return;
        }

        try {
            $categoria = $this->modelo_documento->crearCategoria($nombre);
        } catch (\Throwable $e) {
            error_log('Error al crear categoría: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'No se pudo guardar la categoría.']);
            return;
        }

        if ($categoria === null) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Ya existe una categoría con ese nombre.']);
            return;
        }

        echo json_encode(['success' => true, 'data' => $categoria]);
    }

    private function validarArchivo(array $archivo): array
    {
        $errores = [];
        $maximoBytes = 10 * 1024 * 1024;
        $extensionesPermitidas = ['pdf', 'doc', 'docx', 'odt'];
        $mimesPermitidos = [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword', 'application/octet-stream'],
            'docx' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
                'application/octet-stream',
            ],
            'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip', 'application/octet-stream'],
        ];

        $extension = strtolower(pathinfo((string) ($archivo['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, $extensionesPermitidas, true)) {
            $errores[] = 'El archivo debe ser PDF, DOC, DOCX u ODT.';
        }

        if ((int) ($archivo['size'] ?? 0) > $maximoBytes) {
            $errores[] = 'El archivo no puede superar los 10 MB.';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, (string) $archivo['tmp_name']) : false;
        if ($finfo) {
            finfo_close($finfo);
        }

        if (!$mime || !isset($mimesPermitidos[$extension]) || !in_array($mime, $mimesPermitidos[$extension], true)) {
            $errores[] = 'El tipo de archivo no es válido.';
        }

        return $errores;
    }

    private function mensajeErrorCarga(int $codigo): string
    {
        return match ($codigo) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño máximo permitido.',
            UPLOAD_ERR_PARTIAL => 'La carga del archivo quedó incompleta.',
            UPLOAD_ERR_NO_FILE => 'Seleccioná un archivo para cargar.',
            default => 'Ocurrió un error al cargar el archivo.',
        };
    }

    private function obtenerCiFuncionario(array $usuario): ?int
    {
        $ci = filter_var($usuario['ci'] ?? null, FILTER_VALIDATE_INT);
        if ($ci) {
            return (int) $ci;
        }

        $ciasPorUsuario = [
            'admin' => 11111111,
            'medico' => 22222222,
            'enfermero' => 44444444,
            'soporte' => 11111111,
        ];

        $username = (string) ($usuario['username'] ?? '');
        return isset($ciasPorUsuario[$username]) ? $ciasPorUsuario[$username] : null;
    }

    private function guardarErrorDocumento(string $mensaje): never
    {
        Sesion::guardar('flash_documentos', [
            'tipo' => 'error',
            'mensaje' => $mensaje,
        ]);
        redirigir('/dashboard/documentos');
    }

    /**
     * Muestra el módulo de traslados.
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
