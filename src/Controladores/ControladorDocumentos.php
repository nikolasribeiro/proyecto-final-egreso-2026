<?php

namespace Controladores;

use Modelos\ModeloDocumento;
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

    public function subir(): void
    {
        // 1. Asegurar que la sesión está activa antes de buscar el token
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Limpiar cualquier salida de buffer previa...
        if (ob_get_length()) {
            ob_clean();
        }

        header('Content-Type: application/json');

        // 1. Validar Token CSRF
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (empty($token) || !isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            http_response_code(403);
            echo json_encode(['error' => 'Token CSRF inválido o sesión expirada.']);
            return;
        }

        // 2. Verificar que se haya enviado el archivo
        if (!isset($_FILES['documento'])) {
            http_response_code(400);
            echo json_encode(['error' => 'No se adjuntó ningún archivo PDF.']);
            return;
        }

        try {
            $meta = [
                'id_categoria'   => $_POST['id_categoria'] ?? null,
                'titulo'         => $_POST['titulo'] ?? null,
                'ci_funcionario' => $_SESSION['usuario_ci'] ?? $_SESSION['ci'] ?? $_SESSION['usuario']['ci'] ?? null
            ];

            $id = $this->modelo->subirArchivo($_FILES['documento'], $meta);

            http_response_code(201);
            echo json_encode([
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