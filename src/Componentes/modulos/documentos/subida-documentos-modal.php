<?php

use Nucleo\Sesion;

// La sesión ya está iniciada en src/index.php vía Sesion::iniciar().
$csrfToken = Sesion::generarTokenCsrf();
?>

<div
    id="upload-modal"
    class="modal-overlay"
    onclick="closeModalOnOverlay(event)">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Cargar Nuevo Documento</h3>
            <button class="modal-close" onclick="closeModal('upload-modal')">
                <svg
                    width="16"
                    height="16"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <!-- Alerta para mensajes de error o éxito -->
            <div id="modalFeedback" class="alert" style="display: none; margin-bottom: 1rem;"></div>

            <!-- Zona Drag and Drop -->
            <div
                id="drop-zone"
                class="empty-state"
                style="border-style: dashed; cursor: pointer"
                onclick="document.getElementById('inputDocumento').click()">
                <div class="empty-icon">
                    <svg
                        width="32"
                        height="32"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                </div>
                <p id="drop-zone-title" class="empty-title">Arrastre un archivo aquí</p>
                <p id="drop-zone-text" class="empty-text">o haga clic para seleccionar (solo .pdf)</p>
            </div>

            <!-- Input invisible para seleccionar archivo -->
            <input type="file" id="inputDocumento" accept="application/pdf" style="display: none;">

            <!-- Selector de Categoría (si existen en la vista) -->
            <?php if (isset($categorias) && !empty($categorias)): ?>
                <div class="form-group" style="margin-top: 1rem;">
                    <label for="selectCategoriaModal">Categoría</label>
                    <select id="selectCategoriaModal" class="form-control">
                        <option value="">Seleccione una categoría</option>
                        <?php foreach ($categorias as $catKey => $catVal): ?>
                            <?php
                            // Acepta dos shapes para no romper la página:
                            // 1) ModeloDocumento::obtenerCategorias(): ['id' => int, 'nombre_categoria' => string]
                            // 2) $categoriasUnicas de inicio.php: ['slug' => 'nombre']
                            if (is_array($catVal)) {
                                $catValue = (string)($catVal['id'] ?? '');
                                $catLabel = (string)($catVal['nombre_categoria'] ?? '');
                            } else {
                                $catValue = (string)$catKey;
                                $catLabel = (string)$catVal;
                            }
                            ?>
                            <option value="<?= e($catValue) ?>"><?= e($catLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <button
                class="btn btn-secondary btn-small"
                onclick="closeModal('upload-modal')">
                Cancelar
            </button>
            <button id="btnSubirDocumento" class="btn btn-primary btn-small">Subir Documento</button>
        </div>
    </div>
</div>

<script src="/assets/javascript/documentos/subida-documentos.js"></script>