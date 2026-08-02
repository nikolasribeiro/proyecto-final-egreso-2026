<?php

declare(strict_types=1);

namespace Nucleo\Constantes;

/**
 * Mock de usuarios del sistema. Incluye fecha_baja para soportar
 * la baja lógica (soft delete) requerida por Soporte Técnico.
 *
 * NUNCA se hace unset() sobre este array: la baja se representa
 * seteando fecha_baja a un string ISO (o null para reactivar).
 */
final class Usuarios
{
    /**
     * Estado mutable en memoria: persistencia de las bajas lógicas
     * durante la vida del proceso PHP (mock, no hay DB).
     * Formato: [username => ['fecha_baja' => string|null]].
     *
     * @var array<string, array<string, string|null>>
     */
    private static array $estado = [];

    /**
     * Devuelve el array de usuarios mock, aplicando las bajas lógicas
     * que se hayan hecho en el proceso actual.
     *
     * Estructura:
     *   - username   : identificador único
     *   - nombre     : nombre completo legible
     *   - rol        : clave de rol (administrador, medico, enfermero, soporte_tecnico)
     *   - email      : correo de contacto
     *   - fecha_alta : fecha de alta en formato Y-m-d
     *   - fecha_baja : fecha de baja en formato Y-m-d, o null si está activo
     *
     * @return array<int, array<string, mixed>>
     */
    public static function todos(): array
    {
        $base = [
            [
                'username'   => 'admin',
                'nombre'     => 'Ana Administradora',
                'rol'        => 'administrador',
                'email'      => 'admin@hospitalclinicas.uy',
                'fecha_alta' => '2022-03-15',
                'fecha_baja' => null,
            ],
            [
                'username'   => 'medico',
                'nombre'     => 'Dr. Martín Méndez',
                'rol'        => 'medico',
                'email'      => 'martin.mendez@hospitalclinicas.uy',
                'fecha_alta' => '2023-06-01',
                'fecha_baja' => '2025-09-12',
            ],
            [
                'username'   => 'enfermero',
                'nombre'     => 'Lic. Lucía Fernández',
                'rol'        => 'enfermero',
                'email'      => 'lucia.fernandez@hospitalclinicas.uy',
                'fecha_alta' => '2023-09-10',
                'fecha_baja' => null,
            ],
            [
                'username'   => 'soporte',
                'nombre'     => 'Sergio Soporte',
                'rol'        => 'soporte_tecnico',
                'email'      => 'soporte@hospitalclinicas.uy',
                'fecha_alta' => '2024-01-20',
                'fecha_baja' => null,
            ],
        ];

        // Aplica las bajas lógicas del estado mutable
        foreach ($base as &$usuario) {
            if (array_key_exists($usuario['username'], self::$estado)) {
                $usuario['fecha_baja'] = self::$estado[$usuario['username']]['fecha_baja'];
            }
        }
        unset($usuario);

        return $base;
    }

    /**
     * Busca un usuario por username. Devuelve null si no existe.
     */
    public static function buscar(string $username): ?array
    {
        foreach (self::todos() as $usuario) {
            if ($usuario['username'] === $username) {
                return $usuario;
            }
        }
        return null;
    }

    /**
     * Indica si el usuario está activo (sin fecha_baja).
     */
    public static function esActivo(array $usuario): bool
    {
        return empty($usuario['fecha_baja']);
    }

    /**
     * Da de baja (lógicamente) a un usuario.
     * NUNCA lo borra del array: solo setea fecha_baja.
     *
     * @return array{success: bool, message: string}
     */
    public static function darBaja(string $username): array
    {
        $usuario = self::buscar($username);
        if (!$usuario) {
            return ['success' => false, 'message' => "Usuario '$username' no encontrado."];
        }
        if (!empty($usuario['fecha_baja'])) {
            return ['success' => false, 'message' => "El usuario '$username' ya estaba dado de baja."];
        }

        self::$estado[$username] = ['fecha_baja' => date('Y-m-d')];
        return ['success' => true, 'message' => "Usuario '$username' dado de baja correctamente."];
    }

    /**
     * Reactiva un usuario previamente dado de baja
     * (limpia fecha_baja → null).
     *
     * @return array{success: bool, message: string}
     */
    public static function reactivar(string $username): array
    {
        $usuario = self::buscar($username);
        if (!$usuario) {
            return ['success' => false, 'message' => "Usuario '$username' no encontrado."];
        }
        if (empty($usuario['fecha_baja'])) {
            return ['success' => false, 'message' => "El usuario '$username' ya estaba activo."];
        }

        self::$estado[$username] = ['fecha_baja' => null];
        return ['success' => true, 'message' => "Usuario '$username' reactivado correctamente."];
    }
}
