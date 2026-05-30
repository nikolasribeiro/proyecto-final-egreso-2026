<section id="documents" class="section active">
    <div class="section-header">
        <div>
            <h2 class="section-title">Gestion de Documentos</h2>
            <p class="section-description">
                Administre y genere codigos QR para sus documentos
            </p>
        </div>
        <button
            class="btn btn-primary"
            onclick="openModal('upload-modal')">
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
            Cargar Nuevo Documento
        </button>
    </div>

    <div class="table-container">
        <div class="table-responsive">
            <table class="documents-table">
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Tamano</th>
                        <th>Fecha de Subida</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Document Row 1 -->
                    <tr>
                        <td>
                            <div class="document-cell">
                                <div class="document-icon">
                                    <svg
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="document-name">
                                        Protocolo de Emergencias 2024
                                    </div>
                                    <div class="document-type">PDF</div>
                                </div>
                            </div>
                        </td>
                        <td data-label="Tamano">
                            <span class="document-size">2.4 MB</span>
                        </td>
                        <td data-label="Fecha">
                            <span class="document-date">Hace 2 dias</span>
                        </td>
                        <td data-label="Acciones">
                            <button
                                class="btn btn-secondary btn-small"
                                onclick="openQRModal('Protocolo de Emergencias 2024')">
                                <svg
                                    class="icon"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h2M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                </svg>
                                Generar QR
                            </button>
                        </td>
                    </tr>

                    <!-- Document Row 2 -->
                    <tr>
                        <td>
                            <div class="document-cell">
                                <div class="document-icon">
                                    <svg
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="document-name">
                                        Manual de Procedimientos Quirurgicos
                                    </div>
                                    <div class="document-type">PDF</div>
                                </div>
                            </div>
                        </td>
                        <td data-label="Tamano">
                            <span class="document-size">5.1 MB</span>
                        </td>
                        <td data-label="Fecha">
                            <span class="document-date">Hace 1 semana</span>
                        </td>
                        <td data-label="Acciones">
                            <button
                                class="btn btn-secondary btn-small"
                                onclick="
                            openQRModal('Manual de Procedimientos Quirurgicos')
                          ">
                                <svg
                                    class="icon"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h2M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                </svg>
                                Generar QR
                            </button>
                        </td>
                    </tr>

                    <!-- Document Row 3 -->
                    <tr>
                        <td>
                            <div class="document-cell">
                                <div class="document-icon">
                                    <svg
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="document-name">
                                        Formulario de Consentimiento
                                    </div>
                                    <div class="document-type">PDF</div>
                                </div>
                            </div>
                        </td>
                        <td data-label="Tamano">
                            <span class="document-size">156 KB</span>
                        </td>
                        <td data-label="Fecha">
                            <span class="document-date">Hace 3 semanas</span>
                        </td>
                        <td data-label="Acciones">
                            <button
                                class="btn btn-secondary btn-small"
                                onclick="openQRModal('Formulario de Consentimiento')">
                                <svg
                                    class="icon"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h2M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                </svg>
                                Generar QR
                            </button>
                        </td>
                    </tr>

                    <!-- Document Row 4 -->
                    <tr>
                        <td>
                            <div class="document-cell">
                                <div class="document-icon">
                                    <svg
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="document-name">
                                        Guia de Seguridad del Paciente
                                    </div>
                                    <div class="document-type">PDF</div>
                                </div>
                            </div>
                        </td>
                        <td data-label="Tamano">
                            <span class="document-size">890 KB</span>
                        </td>
                        <td data-label="Fecha">
                            <span class="document-date">Hace 1 mes</span>
                        </td>
                        <td data-label="Acciones">
                            <button
                                class="btn btn-secondary btn-small"
                                onclick="
                            openQRModal('Guia de Seguridad del Paciente')
                          ">
                                <svg
                                    class="icon"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h2M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                </svg>
                                Generar QR
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- QR Code Modal -->
<?php componente('modulos/documentos/qr-modal') ?>

<!-- Upload Document Modal -->
<?php componente('modulos/documentos/subida-documentos-modal') ?>