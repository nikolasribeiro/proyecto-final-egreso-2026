<?php

namespace Nucleo;

class RutaProtegida
{
    /**
     * Este constructor se ejecuta automáticamente cada vez que el 
     * enrutador instancia un controlador que hereda de esta clase.
     * * @param array $rolesPermitidos Lista de roles que pueden acceder. Si está vacío, solo requiere login.
     */
    public function __construct(array $rolesPermitidos = [])
    {
        // 1. Verificamos si existe el usuario en sesión
        // Recuerda que en ControladorAuth guardas un array 'user' con 'rol'
        $usuario = Sesion::obtener('user');

        if (!$usuario) {
            // El usuario no está logueado, lo expulsamos al login
            Sesion::guardar('error_login', 'Vuelve a iniciar sesion para continuar');
            redirigir('/login?error=auth');
        }

        // 2. Verificamos la autorización de roles (si se especificaron)
        if (!empty($rolesPermitidos)) {
            $rolUsuario = $usuario['rol'] ?? 'invitado';

            // Si el rol del usuario no está dentro del array de permitidos, bloqueamos
            if (!in_array($rolUsuario, $rolesPermitidos, true)) {
                // Retornamos un 403 Forbidden utilizando tu helper de aborto
                abortar(403);
            }
        }
    }
}
