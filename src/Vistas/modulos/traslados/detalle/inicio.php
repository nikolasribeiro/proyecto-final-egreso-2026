<!-- View: Transfer Detail -->
<div id="transfer-detail" class="view-section">
    <a class="back-button" href="/dashboard/traslados">
        <svg
            class="icon"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24">
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M15 19l-7-7 7-7" />
        </svg>
        Volver a la lista
    </a>

    <div class="transfer-detail-section">
        <div class="transfer-detail-header">
            <div class="transfer-detail-info">
                <h3>Traslado #TRF-2024-0891</h3>
                <p>Paciente - Hospital Central - Clinica Norte</p>
            </div>
            <span class="transfer-type-badge badge-patient">Paciente</span>
        </div>

        <!-- Stepper -->
        <div class="stepper">
            <div class="stepper-step completed">
                <div class="stepper-indicator">
                    <svg
                        width="16"
                        height="16"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="3"
                            d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div class="stepper-content">
                    <div class="stepper-title">En transito</div>
                </div>
            </div>
            <div class="stepper-connector completed"></div>

            <div class="stepper-step active">
                <div class="stepper-indicator">2</div>
                <div class="stepper-content">
                    <div class="stepper-title">Arribo al destino</div>
                </div>
            </div>
            <div class="stepper-connector"></div>

            <div class="stepper-step">
                <div class="stepper-indicator">3</div>
                <div class="stepper-content">
                    <div class="stepper-title">En transito regreso</div>
                </div>
            </div>
            <div class="stepper-connector"></div>

            <div class="stepper-step">
                <div class="stepper-indicator">4</div>
                <div class="stepper-content">
                    <div class="stepper-title">Arribo a Central</div>
                </div>
            </div>
        </div>

        <!-- Action Section -->
        <div class="action-section">
            <h4>Proxima accion</h4>
            <button class="btn btn-success btn-large">
                <svg
                    class="icon"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M5 13l4 4L19 7" />
                </svg>
                Registrar Arribo a Destino
            </button>
        </div>

        <!-- Report Section -->
        <div class="report-section">
            <button class="btn btn-danger">
                <svg
                    class="icon"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                Algo salio mal? Generar reporte
            </button>
        </div>
    </div>
</div>