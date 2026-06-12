<div class="form-step" id="step-2">
    <h3 class="step-title">
        <span class="step-number">2</span>
        Datos Clínicos del Paciente
    </h3>
    <p class="step-hint">
        Estos datos son críticos para que el equipo de traslado evalúe la urgencia
        y prepare el vehículo con el equipamiento adecuado.
    </p>

    <div class="clinico-form">
        <div class="clinico-grid">
            <label class="clinico-card">
                <input type="checkbox" id="estado-critico" name="estado_critico" value="1">
                <div class="clinico-card-content">
                    <div class="clinico-icon critico">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
                        </svg>
                    </div>
                    <div class="clinico-card-text">
                        <span class="clinico-titulo">Estado Crítico</span>
                        <span class="clinico-desc">El paciente requiere atención prioritaria.</span>
                    </div>
                </div>
            </label>

            <label class="clinico-card">
                <input type="checkbox" id="requiere-camilla" name="requiere_camilla" value="1">
                <div class="clinico-card-content">
                    <div class="clinico-icon camilla">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-7l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                    </div>
                    <div class="clinico-card-text">
                        <span class="clinico-titulo">Requiere Camilla</span>
                        <span class="clinico-desc">El paciente no puede viajar sentado.</span>
                    </div>
                </div>
            </label>
        </div>

        <div class="form-group">
            <label for="tipo-diagnostico" class="form-label">Tipo de Diagnóstico</label>
            <select id="tipo-diagnostico" name="tipo_diagnostico" class="form-select">
                <option value="">-- Sin diagnóstico especificado --</option>
                <option value="Cardiológico">Cardiológico</option>
                <option value="Neurológico">Neurológico</option>
                <option value="Traumatológico">Traumatológico</option>
                <option value="Oncológico">Oncológico</option>
                <option value="Respiratorio">Respiratorio</option>
                <option value="Otro">Otro</option>
            </select>
            <p class="step-hint">Opcional. Útil para preparar el equipamiento específico en el vehículo.</p>
        </div>
    </div>

    <div class="step-actions">
        <button type="button" class="btn btn-outline btn-lg" id="btn-back-2">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Volver
        </button>
        <button type="button" class="btn btn-primary btn-lg" id="btn-step-2">
            Continuar
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </button>
    </div>
</div>
