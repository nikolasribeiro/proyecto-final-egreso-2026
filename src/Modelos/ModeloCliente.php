<?php

declare(strict_types=1);

namespace Modelos;

use Nucleo\Bd;

class ModeloCliente
{
    /**
     * Obtiene todos los clientes de la base de datos.
     * 
     * @return array Lista de clientes
     */
    public static function obtenerTodos(): array
    {
        $bd = Bd::getInstancia();
        return $bd->seleccionar("SELECT id, nombre, email, telefono, created_at FROM clientes ORDER BY created_at DESC");
    }

    /**
     * Obtiene un cliente por su ID.
     * 
     * @param int $id ID del cliente
     * @return array|null Datos del cliente o null si no existe
     */
    public static function obtenerPorId(int $id): ?array
    {
        $bd = Bd::getInstancia();
        return $bd->seleccionarUno("SELECT id, nombre, email, telefono, created_at FROM clientes WHERE id = ?", [$id]);
    }

    /**
     * Crea un nuevo cliente en la base de datos.
     * 
     * @param string $nombre Nombre del cliente
     * @param string $email Email del cliente
     * @param string $telefono Teléfono del cliente
     * @return string|false ID del cliente creado o false si falla
     */
    public static function crear(string $nombre, string $email, string $telefono): string|false
    {
        $bd = Bd::getInstancia();
        return $bd->insertar(
            "INSERT INTO clientes (nombre, email, telefono) VALUES (?, ?, ?)",
            [$nombre, $email, $telefono]
        );
    }

    /**
     * Actualiza un cliente existente.
     * 
     * @param int $id ID del cliente
     * @param string $nombre Nuevo nombre
     * @param string $email Nuevo email
     * @param string $telefono Nuevo teléfono
     * @return int Filas modificadas
     */
    public static function actualizar(int $id, string $nombre, string $email, string $telefono): int
    {
        $bd = Bd::getInstancia();
        return $bd->actualizar(
            "UPDATE clientes SET nombre = ?, email = ?, telefono = ? WHERE id = ?",
            [$nombre, $email, $telefono, $id]
        );
    }

    /**
     * Elimina un cliente por su ID.
     * 
     * @param int $id ID del cliente
     * @return int Filas eliminadas
     */
    public static function eliminar(int $id): int
    {
        $bd = Bd::getInstancia();
        return $bd->eliminar("DELETE FROM clientes WHERE id = ?", [$id]);
    }

    /**
     * Busca clientes por nombre o email.
     * 
     * @param string $busqueda Término de búsqueda
     * @return array Resultados de la búsqueda
     */
    public static function buscar(string $busqueda): array
    {
        $bd = Bd::getInstancia();
        $termino = "%{$busqueda}%";
        return $bd->seleccionar(
            "SELECT id, nombre, email, telefono FROM clientes WHERE nombre LIKE ? OR email LIKE ?",
            [$termino, $termino]
        );
    }
}
