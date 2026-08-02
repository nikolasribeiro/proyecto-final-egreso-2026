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
            <div
                class="empty-state"
                style="border-style: dashed; cursor: pointer">
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
                <p class="empty-title">Arrastre un archivo aqui</p>
                <p class="empty-text">o haga clic para seleccionar</p>
            </div>
        </div>
        <div class="modal-footer">
            <button
                class="btn btn-secondary btn-small"
                onclick="closeModal('upload-modal')">
                Cancelar
            </button>
            <button class="btn btn-primary btn-small">Subir Documento</button>
        </div>
    </div>
</div>