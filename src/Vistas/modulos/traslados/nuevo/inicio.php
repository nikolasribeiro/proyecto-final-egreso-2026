<!-- View: New Transfer Form -->
<div id="new-transfer" class="view-section">
    <a class="back-button" href="/dashboard/traslados">
        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Volver a la lista
    </a>

    <!-- Step Progress Bar -->
    <?php componente('modulos/traslados/stepper/solicitud/steps') ?>

    <div class="card wizard-card">
        <div class="wizard-header">
            <h2 class="transfer-title">Nueva Solicitud de Traslado</h2>
            <button
                type="button"
                id="btn-reiniciar-solicitud"
                class="btn btn-outline btn-small"
                title="Volver al paso 1 y limpiar toda la selección">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Reiniciar solicitud
            </button>
        </div>

        <!-- CSRF global para POST final (también inyectado en step7) -->
        <input type="hidden" id="csrf-token" value="<?= e($csrf ?? '') ?>">

        <!-- ========== STEP 1: TIPO DE TRASLADO ========== -->
        <?php componente("modulos/traslados/stepper/solicitud/step1-tipo-traslado") ?>

        <!-- ========== STEP 2: DATOS CLÍNICOS ========== -->
        <?php componente("modulos/traslados/stepper/solicitud/step2-datos-clinicos") ?>

        <!-- ========== STEP 3: ORIGEN ========== -->
        <?php componente("modulos/traslados/stepper/solicitud/step3-origen", [
            'origen_id' => 1,
        ]) ?>

        <!-- ========== STEP 4: DESTINOS ========== -->
        <?php componente("modulos/traslados/stepper/solicitud/step4-destinos", [
            'ubicaciones' => $ubicaciones ?? [],
            'origen_id'   => 1,
        ]) ?>

        <!-- ========== STEP 5: PERSONAL ========== -->
        <?php componente("modulos/traslados/stepper/solicitud/step5-personal", [
            'choferes'   => $choferes ?? [],
            'enfermeros' => $enfermeros ?? [],
        ]) ?>

        <!-- ========== STEP 6: VEHÍCULO ========== -->
        <?php componente("modulos/traslados/stepper/solicitud/step6-vehiculo", [
            'vehiculos' => $vehiculos ?? [],
        ]) ?>

        <!-- ========== STEP 7: CONFIRMACION ========== -->
        <?php componente("modulos/traslados/stepper/solicitud/step7-confirmacion") ?>
    </div>
</div>


<script>window.ORIGEN_ID = <?= json_encode((int)($origen_id ?? 1)) ?>;</script>
<script src="/assets/javascript/dashboard/nuevo-traslado.js"></script>