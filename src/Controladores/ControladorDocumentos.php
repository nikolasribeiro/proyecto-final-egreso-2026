<?php

namespace Controladores;

use Modelos\ModeloDocumento;

class ControladorDocumentos
{
    private ModeloDocumento $modelo;

    public function __construct()
    {
        $this->modelo = new ModeloDocumento();
    }

    public function inicio(): void
    {
        // Consultamos la base de datos a través del Modelo
        $documentos = $this->modelo->obtenerTodos();
        $categorias = $this->modelo->obtenerCategorias();

        // Le pasamos los datos reales a tu helper vista()
        vista("modulos/documentos/inicio", [
            "titulo_pagina" => "Gestión de Documentos",
            "documentos"     => $documentos,
            "categorias"     => $categorias
        ], "admin");
    }
}