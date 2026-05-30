<?php

/**
 * @var array $traslados
 */

$traslados = [
    [
        'id' => 'TRF-2024-0891',
        'tipo' => 'Paciente',
        'tamano' => '2.4 MB',
        'fecha_subida' => 'Hace 2 dias',
        'ruta' => '/uploads/protocolo_emergencia.pdf'
    ],
];

?>

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
            <a
                class="btn btn-primary"
                href="/dashboard/traslados/nuevo">
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
            </a>
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
            <a
                class="card transfer-card"
                href="/dashboard/traslados/1"
                style="cursor: pointer">
                <div class="transfer-header">
                    <span class="transfer-type-badge badge-patient">$tipo</span>
                    <span class="transfer-id">$id</span>
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
                        $ubicacion_origen - $ubicacion_destino
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
                        Chofer: $chofer
                    </div>
                </div>
                <div class="transfer-status">
                    <span class="status-dot"></span>
                    $estado
                </div>
            </a>

        </div>
    </div>
</section>