<!-- QR Code Modal -->
<div
    id="qr-modal"
    class="modal-overlay"
    onclick="closeModalOnOverlay(event)">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Codigo QR del Documento</h3>
            <button class="modal-close" onclick="closeModal('qr-modal')">
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
                        src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=placeholder-url"
                        alt="Codigo QR"
                        width="200"
                        height="200" />
                </div>
                <p class="qr-document-name" id="qr-document-name">
                    Nombre del documento
                </p>
                <p style="font-size: 0.875rem; color: var(--secondary-gray)">
                    Escanee este codigo para acceder al documento
                </p>
            </div>
        </div>
        <div class="modal-footer">
            <button
                class="btn btn-secondary btn-small"
                onclick="closeModal('qr-modal')">
                Cerrar
            </button>
            <button class="btn btn-primary btn-small">
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
                Descargar QR
            </button>
        </div>
    </div>
</div>