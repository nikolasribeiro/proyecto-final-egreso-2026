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

        <!-- ========== STEP 1: TIPO DE TRASLADO ========== -->
        <?php componente("modulos/traslados/stepper/solicitud/step1-tipo-traslado") ?>

        <!-- ========== STEP 2: ORIGEN ========== -->
        <?php componente("modulos/traslados/stepper/solicitud/step2-origen") ?>

        <!-- ========== STEP 3: DESTINOS ========== -->
        <?php componente("modulos/traslados/stepper/solicitud/step3-destinos") ?>

        <!-- ========== STEP 4: PERSONAL ========== -->
        <?php componente("modulos/traslados/stepper/solicitud/step4-personal") ?>

        <!-- ========== STEP 5: VEHÍCULO ========== -->
        <?php componente("modulos/traslados/stepper/solicitud/step5-vehiculo") ?>

        <!-- ========== STEP 6: CONFIRMACION ========== -->
        <?php componente("modulos/traslados/stepper/solicitud/step6-confirmacion") ?>
    </div>
</div>


<script src="/assets/javascript/dashboard/nuevo-traslado.js"></script>