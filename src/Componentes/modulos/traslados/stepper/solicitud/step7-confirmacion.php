<div class="form-step" id="step-7">
    <h3 class="step-title">
        <span class="step-number">7</span>
        Confirmar Solicitud
    </h3>

    <div class="resumen-traslado" id="resumen-traslado">
        <div class="resumen-row">
            <span class="resumen-label">Tipo de Traslado:</span>
            <span class="resumen-value" id="resumen-tipo">-</span>
        </div>

        <!-- Datos clínicos (Paso 2) -->
        <div class="resumen-row" id="resumen-estado-critico-row" style="display: none;">
            <span class="resumen-label">Estado Crítico:</span>
            <span class="resumen-value" id="resumen-estado-critico">-</span>
        </div>
        <div class="resumen-row" id="resumen-camilla-row" style="display: none;">
            <span class="resumen-label">Requiere Camilla:</span>
            <span class="resumen-value" id="resumen-camilla">-</span>
        </div>
        <div class="resumen-row" id="resumen-diagnostico-row" style="display: none;">
            <span class="resumen-label">Tipo de Diagnóstico:</span>
            <span class="resumen-value" id="resumen-diagnostico">-</span>
        </div>

        <div class="resumen-row">
            <span class="resumen-label">Origen:</span>
            <span class="resumen-value">Hospital de Clínicas</span>
        </div>
        <div class="resumen-row">
            <span class="resumen-label">Destinos:</span>
            <span class="resumen-value" id="resumen-destinos">-</span>
        </div>
        <div class="resumen-row">
            <span class="resumen-label">Conductor:</span>
            <span class="resumen-value" id="resumen-conductor">-</span>
        </div>
        <div class="resumen-row" id="resumen-enfermero-row" style="display: none;">
            <span class="resumen-label">Enfermero:</span>
            <span class="resumen-value" id="resumen-enfermero">-</span>
        </div>
        <div class="resumen-row" id="resumen-jerarquia-row" style="display: none;">
            <span class="resumen-label">Jerarquía de Enfermería:</span>
            <span class="resumen-value" id="resumen-jerarquia">-</span>
        </div>
        <div class="resumen-row">
            <span class="resumen-label">Vehículo:</span>
            <span class="resumen-value" id="resumen-vehiculo">-</span>
        </div>
        <div class="resumen-row" id="resumen-vuelta-row" style="display: none;">
            <span class="resumen-label">Regresa al origen:</span>
            <span class="resumen-value">Sí</span>
        </div>
    </div>

    <div class="step-actions">
        <button type="button" class="btn btn-outline btn-lg" id="btn-back-7">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Volver
        </button>
        <button type="button" class="btn btn-success btn-lg" id="btn-confirmar-traslado">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            Confirmar Traslado
        </button>
    </div>
</div>
