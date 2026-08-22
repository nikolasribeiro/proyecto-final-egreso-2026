<?php

use Nucleo\Sesion;

$csrfToken = Sesion::generarTokenCsrf();
?>

<div
    id="eliminar-documento-modal"
    class="modal-overlay"
    onclick="closeModalOnOverlay(event)">
    <div class="modal" style="max-width: 420px;">
        <div class="modal-header">
            <h3 class="modal-title">Eliminar documento</h3>
            <button class="modal-close" onclick="closeModal('eliminar-documento-modal')">
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
            <div class="modal-confirm-icon" style="background: var(--danger-red-light); color: var(--danger-red);">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3" />
                </svg>
            </div>
            <p style="margin: 0 0 var(--space-2); text-align: center;">
                ¿Estás seguro de que querés eliminar el documento
                <strong id="eliminarDocumentoNombre">—</strong>?
            </p>
            <p class="modal-confirm-text" style="text-align: center;">
                El documento dejará de aparecer en la lista y en los QR, pero
                el archivo se conserva para auditoría. Esta acción puede
                revertirse desde la base de datos.
            </p>
            <input type="hidden" id="eliminarDocumentoId" value="">
            <input type="hidden" id="eliminarDocumentoCsrf" value="<?= e($csrfToken) ?>">
        </div>
        <div class="modal-footer">
            <button
                type="button"
                class="btn btn-secondary btn-small"
                onclick="closeModal('eliminar-documento-modal')">
                Cancelar
            </button>
            <button
                type="button"
                id="btnConfirmarEliminar"
                class="btn btn-danger btn-small">
                Eliminar
            </button>
        </div>
    </div>
</div>
