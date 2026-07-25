<?php

declare(strict_types=1);

namespace Controladores;

use Nucleo\Sesion;

/**
 * Endpoint interno invocado por nginx (`auth_request`) para
 * autenticar peticiones hacia Zabbix sin pedir credenciales al
 * usuario. Si la sesión PHP es válida y el rol es tecnico o admin,
 * nginx inyecta el email en `REMOTE_USER` y Zabbix deja entrar.
 *
 * Este endpoint NO debe estar expuesto públicamente — nginx lo llama
 * como subrequest interno (`location = /_auth/zabbix { internal; }`).
 *
 * Devuelve:
 *   - 200 con `X-Remote-User: <email>` si la sesión es válida.
 *   - 401 si no hay sesión.
 *   - 403 si la sesión existe pero el rol no está permitido.
 */
final class ControladorZabbixSSO
{
    public function validar(): void
    {
        $usuario = Sesion::usuario();

        if ($usuario === null) {
            http_response_code(401);
            return;
        }

        if (!in_array($usuario['rol'], ['admin', 'tecnico'], true)) {
            http_response_code(403);
            return;
        }

        $email = filter_var($usuario['email'], FILTER_VALIDATE_EMAIL);
        if ($email === false) {
            http_response_code(403);
            return;
        }

        // Defensa contra header injection: impedir CRLF en el email.
        // FILTER_VALIDATE_EMAIL ya garantiza formato válido, pero
        // añadimos una validación extra por si acaso.
        if (preg_match('/[\r\n]/', $email) === 1) {
            http_response_code(403);
            return;
        }

        // Zabbix 7.0 HTTP Auth parsea REMOTE_USER con un parser
        // estricto (`CADNameAttributeParser`) que separa `user@domain`.
        // Para evitar problemas con dominios y mantener el username
        // corto compatible con la convención de Zabbix, enviamos
        // únicamente la parte local del email.
        $username = strstr($email, '@', true);
        if ($username === false || $username === '') {
            $username = $email;
        }

        header('X-Remote-User: ' . $username);
        header('Cache-Control: no-store, private');
        header('Pragma: no-cache');
        http_response_code(200);
    }
}
