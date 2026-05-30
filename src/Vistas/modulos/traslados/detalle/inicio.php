<!-- View: Transfer Detail - One Button Workflow -->
<div id="transfer-detail"
 class="view-section"
     data-traslado-id="<?= $traslado_id ?>"
     data-numero="<?= isset($traslado_data) ? htmlspecialchars($traslado_data['numero']) : 'TRF-2024-0891' ?>"
     data-paciente="<?= isset($traslado_data) ? htmlspecialchars($traslado_data['paciente']) : 'Juan Pérez' ?>"
     data-origen="<?= isset($traslado_data) ? htmlspecialchars($traslado_data['origen']) : 'Hospital Central' ?>"
     data-conductor="<?= isset($traslado_data) ? htmlspecialchars($traslado_data['conductor']) : 'Carlos López' ?>"
     data-vehiculo="<?= isset($traslado_data) ? htmlspecialchars($traslado_data['vehiculo']) : 'Ambulancia 001' ?>"
     data-tipo="<?= isset($traslado_data) ? htmlspecialchars($traslado_data['tipo']) : 'paciente_alta' ?>">

    <a class="back-button" href="/dashboard/traslados">
        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Volver a la lista
    </a>

    <div class="transfer-detail-section">
        <!-- Header with transfer info -->
        <div class="transfer-detail-header">
            <div class="transfer-detail-info">
                <h3>Traslado #<?= isset($traslado_data) ? htmlspecialchars($traslado_data['numero']) : 'TRF-2024-0891' ?></h3>
                <p>Cargando información...</p>
            </div>
            <span class="transfer-type-badge badge-patient">Paciente</span>
        </div>

        <!-- Meta info (conductor, vehículo) -->
        <div class="transfer-detail-meta"></div>

        <!-- Dynamic Stepper -->
        <div class="detail-stepper">
            <!-- Rendered by JavaScript -->
        </div>

        <!-- Action Section - ONE BUTTON -->
        <div class="detail-action-section">
            <h4>Próxima acción</h4>
            <button id="btn-main-action" class="btn btn-success btn-large" disabled>
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Cargando...
            </button>
        </div>

        <!-- Report Section -->
        <div class="detail-report-section">
            <button id="btn-report" class="btn btn-danger">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                Algo salió mal? Generar reporte
            </button>
        </div>
    </div>

    <!-- Report Modal -->
    <?php componente('modulos/traslados/detalle/report-modal') ?>
</div>

<script src="/assets/javascript/dashboard/detalle-traslado.js"></script>
