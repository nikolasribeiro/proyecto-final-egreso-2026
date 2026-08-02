<?php

/**
 * @var array $categorias
 * @var string $csrf_token
 * @var bool $puede_crear_documentos
 */
$categorias = $categorias ?? [];
$csrfToken = $csrf_token ?? '';
$puedeCrearDocumentos = $puede_crear_documentos ?? false;
?>

<div
    id="upload-modal"
    class="modal-overlay"
    onclick="closeModalOnOverlay(event)">
    <div class="modal modal--upload">
        <div class="modal-header">
            <h3 class="modal-title">Cargar Nuevo Documento</h3>
            <button
                class="modal-close"
                type="button"
                aria-label="Cerrar"
                onclick="closeModal('upload-modal')">
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

        <form
            id="upload-document-form"
            action="/dashboard/documentos"
            method="POST"
            enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <div class="modal-body upload-modal-body">
                <div class="form-group">
                    <label class="form-label" for="document-title">Título del documento</label>
                    <input
                        id="document-title"
                        class="form-input"
                        type="text"
                        name="titulo"
                        maxlength="200"
                        required
                        placeholder="Ej. Protocolo de atención 2026">
                </div>

                <div class="form-group">
                    <label class="form-label" for="document-category">Categoría</label>
                    <div class="upload-category-picker">
                        <div id="category-select-wrapper" class="upload-category-select-wrapper">
                            <select
                                id="document-category"
                                class="form-select"
                                name="id_categoria"
                                required
                                <?= empty($categorias) ? 'disabled' : '' ?>>
                                <option value="" selected disabled>Seleccioná una categoría</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?= e((string) $categoria['id']) ?>">
                                        <?= e($categoria['nombre_categoria']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($puedeCrearDocumentos): ?>
                                <button
                                    id="add-category-button"
                                    class="btn btn-icon upload-category-add"
                                    type="button"
                                    aria-label="crear nueva categoria"
                                    data-tooltip="crear nueva categoria">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14" />
                                    </svg>
                                </button>
                            <?php endif; ?>
                        </div>

                        <div id="category-create-wrapper" class="upload-category-create-wrapper" hidden>
                            <input
                                id="new-category-name"
                                class="form-input"
                                type="text"
                                maxlength="100"
                                placeholder="Nombre de la nueva categoría"
                                aria-label="Nombre de la nueva categoría">
                            <div class="upload-category-actions">
                                <button
                                    id="cancel-category-button"
                                    class="btn btn-icon upload-category-cancel"
                                    type="button"
                                    aria-label="Cancelar creación de categoría">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                                <button
                                    id="save-category-button"
                                    class="btn btn-icon upload-category-confirm"
                                    type="button"
                                    aria-label="Guardar categoría">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php if (empty($categorias)): ?>
                        <p class="form-hint">Creá una categoría para poder cargar el documento.</p>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="document-file">Archivo</label>
                    <div class="form-file upload-file-field">
                        <input
                            id="document-file"
                            class="form-file-input"
                            type="file"
                            name="archivo"
                            accept=".pdf,.doc,.docx,.odt,application/pdf"
                            required>
                        <label id="upload-dropzone" class="form-file-label upload-dropzone" for="document-file">
                            <span class="empty-icon" aria-hidden="true">
                                <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 0115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                            </span>
                            <span id="upload-file-name" class="upload-file-name">Arrastrá un archivo aquí o hacé clic para seleccionar</span>
                            <span class="upload-file-hint">PDF, DOC, DOCX u ODT · máximo 10 MB</span>
                        </label>
                    </div>
                    <p id="upload-file-error" class="form-error" role="alert" hidden></p>
                </div>

                <div id="upload-toast" class="upload-toast" role="status" aria-live="polite" hidden></div>
            </div>

            <div class="modal-footer">
                <button
                    class="btn btn-secondary btn-small"
                    type="button"
                    onclick="closeModal('upload-modal')">
                    Cancelar
                </button>
                <?php if ($puedeCrearDocumentos): ?>
                    <button id="upload-submit-button" class="btn btn-primary btn-small" type="submit">
                        Subir Documento
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>