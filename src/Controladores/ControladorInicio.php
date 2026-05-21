<?php

namespace Controladores;

use Nucleo\Vista;
use Modelos\ModeloCliente;

class ControladorInicio
{
    public function inicio(): void
    {
        try {
            $nuevoCliente = ModeloCliente::crear();
            Vista::mostrar('inicio', ["nombreCliente" => $nuevoCliente]);
        } catch (\Throwable $th) {
            Vista::mostrar('errores/error_servidor');
        }
    }

    public function prueba(): void
    {
        try {
            vista("prueba/inicio", [
                "prueba" => "pruebaprueba",
            ], "admin");
        } catch (\Throwable $th) {
            vista('errores/error_servidor');
        }
    }

    public function pruebaDetalle(int $id): void
    {
        try {
            vista("prueba/inicio", [
                "prueba" => "pruebaprueba",
                "id" => $id
            ], "admin");
        } catch (\Throwable $th) {
            vista('errores/error_servidor');
        }
    }
}
