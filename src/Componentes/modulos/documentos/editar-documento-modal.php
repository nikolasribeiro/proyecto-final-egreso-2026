<?php

use Nucleo\Sesion;

$csrfToken = Sesion::generarTokenCsrf();

/**
 * Modal de edición de un documento existente. Se rellena por JS con los
 * data-* del botón "Editar" correspondiente (ver tabla/fila.php).
 *
 * @var array $categorias  Lista con shape ['id' => int, 'nombre_categoria' => string, 'slug' => string]
 *                         (viene de ModeloDocumento::obtenerCategorias()).
 */
$categorias = $categorias ?? [];
?>

<div
    id="editar-documento-modal"
    class="modal-overlay"
    onclick="closeModalOnOverlay(event)">
    <div class="modal" style="max-width: 480px;">
        <div class="modal-header">
            <h3 class="modal-title">Editar documento</h3>
            <button class="modal-close" onclick="closeModal('editar-documento-modal')">
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
        <div class="modal-body" style="text-align: left;">
            <div id="editarDocumentoFeedback" class="alert" style="display: none; margin-bottom: 1rem;"></div>

            <form id="editarDocumentoForm" autocomplete="off">
                <input type="hidden" id="editarDocumentoId" name="id" value="">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <div class="form-group">
                    <label class="form-label" for="editarDocumentoTitulo">Título</label>
                    <input
                        type="text"
                        id="editarDocumentoTitulo"
                        name="titulo"
                        class="form-input"
                        required
                        maxlength="200">
                </div>

                <div class="form-group">
                    <label class="form-label" for="editarDocumentoCategoria">Categoría</label>
                    <select
                        id="editarDocumentoCategoria"
                        name="id_categoria"
                        class="form-select"
                        required>
                        <?php foreach ($categorias as $cat): ?>
                            <?php
                                $catId   = (int)($cat['id'] ?? 0);
                                $catSlug = (string)($cat['slug'] ?? '');
                                $catName = (string)($cat['nombre_categoria'] ?? '');
                            ?>
                            <option value="<?= e((string)$catId) ?>" data-slug="<?= e($catSlug) ?>">
                                <?= e($catName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="editarDocumentoArchivo">Reemplazar PDF (opcional)</label>
                    <input
                        type="file"
                        id="editarDocumentoArchivo"
                        name="documento"
                        class="form-input"
                        accept="application/pdf">
                    <p class="form-hint">
                        Si no seleccionás un archivo, se conserva el PDF actual.
                        Solo se permiten archivos PDF.
                    </p>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button
                type="button"
                class="btn btn-secondary btn-small"
                onclick="closeModal('editar-documento-modal')">
                Cancelar
            </button>
            <button
                type="button"
                id="btnGuardarDocumento"
                class="btn btn-primary btn-small">
                Guardar cambios
            </button>
        </div>
    </div>
</div>
