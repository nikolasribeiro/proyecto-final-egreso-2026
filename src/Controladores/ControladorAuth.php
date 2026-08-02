<?php

namespace Controladores;

use Nucleo\Conexion;
use Nucleo\Sesion;
use PDO;

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
            'admin'     => 'admin123',
            'medico'    => 'medico123',
            'enfermero' => 'enfermero123',
            'soporte'   => 'soporte123',
        ];

        // Mapa explícito username → CI del usuario seedeado (para auditoría).
        // Cualquier username que no esté acá caerá en el fallback.
        $mapaUsuarios = [
            'admin'     => ['ci' => 11111111, 'nombre' => 'Administrador', 'apellido' => 'Prueba'],
            'medico'    => ['ci' => 22222222, 'nombre' => 'Medico',        'apellido' => 'Prueba'],
            'enfermero' => ['ci' => 44444444, 'nombre' => 'Enfermero',     'apellido' => 'Prueba'],
            'soporte'   => ['ci' => 33333333, 'nombre' => 'Chofer',        'apellido' => 'Prueba'],
        ];

        // Mapa explícito username → rol del sistema.
        $mapaRoles = [
            'admin'     => 'administrador',
            'medico'    => 'medico',
            'enfermero' => 'enfermero',
            'soporte'   => 'soporte_tecnico',
        ];

        if (isset($usuariosValidos[$username]) && $usuariosValidos[$username] === $password) {
            // Login exitoso. Buscar id del usuario en la BD para auditoría.
            $infoUsuario = $mapaUsuarios[$username] ?? null;
            $userId = null;

            if ($infoUsuario) {
                try {
                    $db = Conexion::obtenerInstancia();
                    $stmt = $db->prepare("SELECT id FROM usuarios WHERE ci = :ci AND activo = TRUE LIMIT 1");
                    $stmt->execute(['ci' => $infoUsuario['ci']]);
                    $row = $stmt->fetch();
                    if ($row) {
                        $userId = (int)$row['id'];
                    }
                } catch (\Throwable $e) {
                    // Si falla la BD, el id_usuario quedará NULL en la auditoría
                    error_log('Auth: fallo al buscar usuario por CI: ' . $e->getMessage());
                }
            }

            Sesion::guardar('user', [
                'id'        => $userId,
                'username'  => $username,
                'nombre'    => $infoUsuario['nombre'] ?? ucfirst($username),
                'apellido'  => $infoUsuario['apellido'] ?? '',
                'ci'        => $infoUsuario['ci'] ?? null,
                'rol'       => $mapaRoles[$username] ?? 'usuario',
                'login_at'  => date('Y-m-d H:i:s'),
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
