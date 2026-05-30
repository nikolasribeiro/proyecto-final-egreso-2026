<section id="traslados" class="section active">
    <!-- View: Transfer List -->
    <div id="transfer-list" class="view-section">
        <div class="section-header">
            <div>
                <h2 class="section-title">Trazabilidad de Traslados</h2>
                <p class="section-description">
                    Gestione y monitoree los traslados activos
                </p>
            </div>
            <button
                class="btn btn-primary"
                onclick="showView('new-transfer')">
                <svg
                    class="icon"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 4v16m8-8H4" />
                </svg>
                Solicitar Traslado
            </button>
        </div>

        <h3
            style="
                  font-size: 1rem;
                  color: var(--secondary-gray);
                  margin-bottom: 1rem;
                ">
            Traslados Activos
        </h3>

        <div class="card-grid">
            <!-- Active Transfer 1 -->
            <div
                class="card transfer-card"
                onclick="showView('transfer-detail')"
                style="cursor: pointer">
                <div class="transfer-header">
                    <span class="transfer-type-badge badge-patient">Paciente</span>
                    <span class="transfer-id">#TRF-2024-0891</span>
                </div>
                <div class="transfer-details">
                    <div class="transfer-detail-row">
                        <svg
                            class="icon"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Hospital Central - Clinica Norte
                    </div>
                    <div class="transfer-detail-row">
                        <svg
                            class="icon"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Chofer: Juan Perez
                    </div>
                </div>
                <div class="transfer-status">
                    <span class="status-dot"></span>
                    En transito
                </div>
            </div>

            <!-- Active Transfer 2 -->
            <div class="card transfer-card" style="cursor: pointer">
                <div class="transfer-header">
                    <span class="transfer-type-badge badge-equipment">Equipamiento</span>
                    <span class="transfer-id">#TRF-2024-0890</span>
                </div>
                <div class="transfer-details">
                    <div class="transfer-detail-row">
                        <svg
                            class="icon"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Deposito - Hospital Central
                    </div>
                    <div class="transfer-detail-row">
                        <svg
                            class="icon"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Chofer: Maria Garcia
                    </div>
                </div>
                <div class="transfer-status">
                    <span class="status-dot"></span>
                    Arribo al destino
                </div>
            </div>
        </div>
    </div>

    <!-- View: New Transfer Form -->
    <div id="new-transfer" class="view-section">
        <button class="back-button" onclick="showView('transfer-list')">
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
        </button>

        <div class="card">
            <h2
                style="
                    font-size: 1.25rem;
                    font-weight: 700;
                    margin-bottom: 1.5rem;
                  ">
                Nuevo Traslado
            </h2>

            <!-- Alert: No vehicles -->
            <div class="alert alert-warning">
                <div class="alert-content">
                    <svg
                        class="alert-icon"
                        width="24"
                        height="24"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span class="alert-text">No se encuentran vehiculos disponibles en este
                        momento</span>
                </div>
                <button class="btn btn-warning btn-small">
                    Solicitar SAME
                </button>
            </div>

            <form>
                <!-- Step 1: Transfer Type -->
                <div class="progressive-step completed">
                    <div class="form-group">
                        <label class="form-label">Elemento a trasladar</label>
                        <select class="form-select">
                            <option value="">Seleccione una opcion</option>
                            <option value="patient" selected>Paciente</option>
                            <option value="equipment">Equipamiento</option>
                        </select>
                    </div>
                </div>

                <!-- Step 2: Driver -->
                <div class="progressive-step active">
                    <div class="form-group">
                        <label class="form-label">Chofer</label>
                        <select class="form-select">
                            <option value="">Seleccione un chofer</option>
                            <option value="1">Juan Perez</option>
                            <option value="2">Maria Garcia</option>
                            <option value="3">Carlos Lopez</option>
                        </select>
                        <p class="form-hint">
                            Seleccione el chofer asignado al traslado
                        </p>
                    </div>
                </div>

                <!-- Step 3: Nurse (disabled until driver selected) -->
                <div class="progressive-step">
                    <div class="form-group form-disabled">
                        <label class="form-label">Enfermero</label>
                        <div class="progressive-hint">
                            Este campo se habilitara despues de seleccionar el
                            chofer
                        </div>
                    </div>
                </div>

                <!-- Step 4: Additional Details (disabled) -->
                <div class="progressive-step">
                    <div class="form-group form-disabled">
                        <label class="form-label">Detalles adicionales</label>
                        <div class="progressive-hint">
                            Los campos adicionales apareceran secuencialmente al
                            completar los anteriores
                        </div>
                    </div>
                </div>

                <button type="button" class="btn btn-primary btn-large">
                    Confirmar Traslado
                </button>
            </form>
        </div>
    </div>

    <!-- View: Transfer Detail -->
    <div id="transfer-detail" class="view-section">
        <button class="back-button" onclick="showView('transfer-list')">
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
        </button>

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
</section>