<?php

/**
 * Modal genérico para mostrar el QR de una categoría. La URL y el nombre
 * de la categoría se setean por JS (`openCategoryQRModal`) antes de abrir
 * el modal, así un solo modal sirve para cualquier categoría.
 *
 * Para que `downloadQR` y `printQR` (definidas en dashboard.js) funcionen
 * sin tocarse, este modal sigue la convención de setear
 * `window.currentQRData` con los mismos campos que el modal por documento.
 */
?>

<div
    id="qr-modal-categoria"
    class="modal-overlay"
    onclick="closeModalOnOverlay(event)">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Codigo QR de la Categoria</h3>
            <button class="modal-close" onclick="closeModal('qr-modal-categoria')">
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
            <div class="qr-container">
                <div class="qr-code">
                    <img
                        id="qr-categoria-img"
                        src=""
                        alt="Codigo QR"
                        width="200"
                        height="200" />
                </div>
                <p class="qr-document-name" id="qr-categoria-nombre"></p>
                <p style="font-size: 0.875rem; color: var(--secondary-gray); text-align: center; margin-top: 0.5rem;">
                    Escanee este codigo para acceder a la categoria completa de documentos.<br>
                    <span class="qr-url" id="qr-categoria-url"></span>
                </p>
            </div>
        </div>
        <div class="modal-footer">
            <button
                class="btn btn-secondary btn-small"
                onclick="closeModal('qr-modal-categoria')">
                Cerrar
            </button>
            <button
                class="btn btn-outline btn-small"
                onclick="printQR()">
                <svg
                    class="icon"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Imprimir
            </button>
            <button
                class="btn btn-primary btn-small"
                onclick="downloadQR()">
                <svg
                    class="icon"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                Descargar
            </button>
        </div>
    </div>
</div>
