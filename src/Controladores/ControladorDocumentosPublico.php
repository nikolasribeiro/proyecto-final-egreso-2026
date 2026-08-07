<?php
namespace Controladores;

use Nucleo\Vista;
use Modelos\ModeloDocumento;

class ControladorDocumentosPublico {
    
    private $modeloDocumento;

    public function __construct() {
        // Inicializamos el modelo que ya tienes creado
        $this->modeloDocumento = new ModeloDocumento();
    }

    /**
     * Muestra el listado de documentos de una categoría específica accediendo por su slug.
     */
   public function categoriaPorSlug($slug) {
        $this->desactivarCache();

        // Aquí buscaremos los documentos de esta categoría
        $documentos = $this->modeloDocumento->obtenerPorSlugCategoria($slug);

        // Renderizamos una vista nueva (mobile-first)
        Vista::mostrar('modulos/documentos/publico_categoria', [
            'slug' => $slug,
            'documentos' => $documentos
        ]);
    }

    /**
     * Muestra un documento individual para visualizar el PDF.
     */
   /**
     * Muestra un documento individual para visualizar el PDF.
     */
    public function verPorId($id) {
        $this->desactivarCache();

        $documento = $this->modeloDocumento->obtenerPorId($id);

        if (!$documento || empty($documento['ruta_archivo'])) {
            http_response_code(404);
            echo "Documento no encontrado o no disponible.";
            return;
        }

        // Construimos la ruta física al archivo. 
        $rutaFisica = __DIR__ . '/../' . $documento['ruta_archivo'];

        if (file_exists($rutaFisica)) {
            // Cabeceras mágicas para que el celular abra el PDF en el navegador
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . basename($rutaFisica) . '"');
            header('Content-Transfer-Encoding: binary');
            header('Accept-Ranges: bytes');
            
            // Leemos el archivo y lo enviamos a la salida
            readfile($rutaFisica);
        } else {
            http_response_code(404);
            echo "El archivo físico no se encuentra en el servidor.";
        }
    }

    /**
     * Evita que los navegadores móviles guarden los PDFs en caché (Requisito Issue # 110)
     */
    private function desactivarCache() {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
    }
}