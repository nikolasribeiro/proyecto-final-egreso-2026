<?php

namespace Controladores;

use Nucleo\Sesion;

class ControladorAuth
{
    private string $rutaDashboard = '/dashboard/documentos';

    /**
     * Muestra el formulario de login
     */
    public function login(): void
    {

        if (Sesion::obtener('user')) {
            redirigir($this->rutaDashboard);
            return;
        }

        // Generar token CSRF
        $csrfToken = Sesion::generarTokenCsrf();

        // Obtener mensaje de error si existe
        $errorMessage = Sesion::obtener('error_login');
        Sesion::eliminar('error_login');

        vista('auth/login', [
            'csrf_token' => $csrfToken,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Procesa el login (POST)
     */
    public function autenticar(): void
    {
        // Verificar método POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirigir('/login?error=method');
            return;
        }

        // Verificar token CSRF
        $tokenEnviado = $_POST['csrf_token'] ?? '';
        if (!Sesion::validarTokenCsrf($tokenEnviado)) {
            Sesion::guardar('error_login', 'csrf');
            redirigir('/login?error=csrf');
            return;
        }

        // Obtener credenciales
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validar campos vacíos
        if (empty($username) || empty($password)) {
            Sesion::guardar('error_login', 'Por favor, completá todos los campos.');
            redirigir('/login?error=empty');
            return;
        }

        // Aquí iría la validación contra la base de datos
        // Por ahora, usaremos credenciales de prueba
        // TODO: Reemplazar con validación real contra BD

        // Credenciales de prueba (remover en producción)
        $usuariosValidos = [
            'admin' => 'admin123',
            'medico' => 'medico123',
            'enfermero' => 'enfermero123',
        ];

        if (isset($usuariosValidos[$username]) && $usuariosValidos[$username] === $password) {
            // Login exitoso
            Sesion::guardar('user', [
                'username' => $username,
                'nombre' => $username,
                'rol' => $username === 'admin' ? 'administrador' : 'usuario',
                'login_at' => date('Y-m-d H:i:s'),
            ]);

            // Regenerar sesión para prevenir session fixation
            session_regenerate_id(true);

            redirigir($this->rutaDashboard);
        } else {
            // Login fallido
            Sesion::guardar('error_login', 'Credenciales inválidas. Intentá de nuevo.');
            redirigir('/login?error=invalid');
        }
    }

    /**
     * Cierra la sesión
     */
    public function logout(): void
    {
        Sesion::destruir();
        redirigir('/login?message=logout');
    }
}
