<?php

declare(strict_types=1);

namespace Controladores;

use Modelos\ModeloUsuario;
use Nucleo\Sesion;

/**
 * Autenticación de usuarios: login, logout.
 *
 * Vista de login: GET /login
 * Procesar login: POST /login
 * Cerrar sesión: POST /logout
 */
final class ControladorAuth
{
    /**
     * Hash bcrypt "dummy" usado para igualar tiempos de respuesta
     * cuando el email no existe en la BD. Evita enumeración de
     * usuarios por timing differences.
     */
    private const HASH_DUMMY = '$2y$12$CwTycUXWue0Thq9StjUM0uJ8.h5eN1Y7QX3v8hY8C9uT0oq7LbKai';

    public function mostrarLogin(): void
    {
        // Si ya está autenticado, redirigir al inicio.
        if (Sesion::autenticada()) {
            redirigir('/');
        }

        $error = Sesion::obtener('error_login');
        Sesion::eliminar('error_login');

        $tokenCsrf = Sesion::generarTokenCsrf();

        vista('modulos/auth/login', [
            'titulo_pagina' => 'Iniciar sesión',
            'error' => $error,
            'token_csrf' => $tokenCsrf,
        ], 'auth');
    }

    public function login(): void
    {
        // Solo aceptamos POST.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            abortar(405);
        }

        $token = (string) ($_POST['token_csrf'] ?? '');
        if (!Sesion::validarTokenCsrf($token)) {
            Sesion::guardar('error_login', 'Sesión expirada. Recargá la página e intentá de nuevo.');
            redirigir('/login');
        }

        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            Sesion::guardar('error_login', 'Credenciales inválidas.');
            redirigir('/login');
        }

        $usuario = ModeloUsuario::buscarPorEmail($email);

        // Timing constant: si el usuario no existe, igual corremos
        // password_verify contra un hash dummy para no filtrar
        // información por el tiempo de respuesta.
        $hashAComparar = $usuario['password_hash'] ?? self::HASH_DUMMY;
        $passwordOk = ModeloUsuario::verificarPassword($password, $hashAComparar);

        if ($usuario === null || !$passwordOk) {
            Sesion::guardar('error_login', 'Credenciales inválidas.');
            redirigir('/login');
        }

        // Credenciales válidas: autenticar.
        Sesion::autenticar([
            'id' => (int) $usuario['id'],
            'email' => (string) $usuario['email'],
            'nombre' => (string) $usuario['nombre'],
            'rol' => (string) $usuario['rol'],
        ]);

        // Regenerar CSRF para que el token del form de login no sirva
        // en otras requests (defiende contra fixation).
        Sesion::regenerarTokenCsrf();

        redirigir('/');
    }

    public function logout(): void
    {
        // Solo aceptamos POST.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            abortar(405);
        }

        $token = (string) ($_POST['token_csrf'] ?? '');
        if (!Sesion::validarTokenCsrf($token)) {
            redirigir('/login');
        }

        Sesion::destruir();
        redirigir('/login');
    }
}
