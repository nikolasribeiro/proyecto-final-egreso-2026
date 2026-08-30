<?php

namespace Controladores;

use Modelos\ModeloDocumento;
use Nucleo\Sesion;
use Nucleo\Constantes\Roles;
use Exception;

class ControladorDocumentos
{
    private $modelo;

    public function __construct()
    {
        $this->modelo = new ModeloDocumento();
    }

    public function inicio(): void
    {
        redirigir('/dashboard/documentos');
    }

    public function subir()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido.']);
            return;
        }

        try {
            // Obtener el array del usuario autenticado guardado por ControladorAuth / ModeloUsuario
            $usuarioLogueado = Sesion::obtener('user');

            // Extraer la CI (clave 'ci' proveniente de la tabla usuarios)
            $ciFuncionario = $usuarioLogueado['ci'] ?? null;

            if (!$ciFuncionario) {
                http_response_code(401);
                echo json_encode(['error' => 'No hay una sesión activa de funcionario válida.']);
                return;
            }

            // Chequeo de permiso sobre el rol de la sesión. Cubre el caso
            // de un rol sin permiso 'crear' sobre documentos (incluye la
            // cuenta sin rol válido que por algún motivo traiga sesión).
            $rolSesion = $usuarioLogueado['rol'] ?? '';
            if (!Roles::permiso($rolSesion, 'documentos', 'crear')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tiene permisos para subir documentos.']);
                return;
            }

            if (!isset($_FILES['documento']) || $_FILES['documento']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('No se recibió ningún archivo PDF válido.');
            }

            $meta = [
                'id_categoria'   => $_POST['id_categoria'] ?? null,
                'titulo'         => $_POST['titulo'] ?? null,
                'ci_funcionario' => $ciFuncionario
            ];

            $id = $this->modelo->subirArchivo($_FILES['documento'], $meta);

            http_response_code(201);
            return json_encode([
                'exito' => true,
                'mensaje' => 'Documento subido correctamente.',
                'id' => $id
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/documentos/{id}
     * Actualiza un documento: título, categoría y, opcionalmente, el PDF.
     * Pensado para fetch() con FormData, así que el método es POST con
     * un campo _method=PUT no es viable sin reescribir el enrutador.
     * Por convención del proyecto usamos POST para mutaciones con file
     * upload (ver endpoint subir()).
     */
    public function actualizar(string $id)
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['exito' => false, 'mensaje' => 'Método no permitido.']);
            return;
        }

        try {
            $usuario = Sesion::obtener('user');
            $rol     = $usuario['rol'] ?? null;
            $idInt   = (int)$id;

            if (!$idInt) {
                throw new Exception('ID de documento inválido.');
            }

            if (!Roles::permiso($rol, 'documentos', 'editar')) {
                http_response_code(403);
                echo json_encode(['exito' => false, 'mensaje' => 'No tiene permisos para editar documentos.']);
                return;
            }

            $csrf = $_POST['csrf_token'] ?? ($_POST['_csrf'] ?? '');
            if (!Sesion::validarTokenCsrf($csrf)) {
                http_response_code(419);
                echo json_encode(['exito' => false, 'mensaje' => 'Token CSRF inválido.']);
                return;
            }

            $datos = [
                'titulo'       => $_POST['titulo'] ?? '',
                'id_categoria' => $_POST['id_categoria'] ?? null,
                'archivo'      => $_FILES['documento'] ?? null,
            ];

            $resultado = $this->modelo->actualizarDocumento($idInt, $datos);

            http_response_code(200);
            echo json_encode([
                'exito'   => true,
                'mensaje' => 'Documento actualizado correctamente.',
                'data'    => [
                    'id'           => $idInt,
                    'ruta_archivo' => $resultado['ruta_archivo'],
                ],
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['exito' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/documentos/{id}/eliminar
     * Soft delete: marca documento_activo = FALSE. Se conserva el archivo.
     */
    public function eliminar(string $id)
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['exito' => false, 'mensaje' => 'Método no permitido.']);
            return;
        }

        try {
            $usuario = Sesion::obtener('user');
            $rol     = $usuario['rol'] ?? null;
            $idInt   = (int)$id;

            if (!$idInt) {
                throw new Exception('ID de documento inválido.');
            }

            if (!Roles::permiso($rol, 'documentos', 'eliminar')) {
                http_response_code(403);
                echo json_encode(['exito' => false, 'mensaje' => 'No tiene permisos para eliminar documentos.']);
                return;
            }

            $csrf = $_POST['csrf_token'] ?? ($_POST['_csrf'] ?? '');
            if (!Sesion::validarTokenCsrf($csrf)) {
                http_response_code(419);
                echo json_encode(['exito' => false, 'mensaje' => 'Token CSRF inválido.']);
                return;
            }

            $this->modelo->eliminarDocumento($idInt);

            http_response_code(200);
            echo json_encode([
                'exito'   => true,
                'mensaje' => 'Documento eliminado correctamente.',
                'data'    => ['id' => $idInt],
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['exito' => false, 'mensaje' => $e->getMessage()]);
        }
    }
}
