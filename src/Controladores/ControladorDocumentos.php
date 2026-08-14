<?php

namespace Controladores;

use Modelos\ModeloDocumento;
use Nucleo\Sesion;
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
}
