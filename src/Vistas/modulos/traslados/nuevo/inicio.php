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
        <h2 class="transfer-title">Nueva Solicitud de Traslado</h2>

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