<div class="form-step active" id="step-1">
    <h3 class="step-title">
        <span class="step-number">1</span>
        ¿Qué necesitas trasladar?
    </h3>

    <div class="tipo-opciones">
        <label class="tipo-card">
            <input type="radio" name="tipo_traslado" value="paciente_alta" required>
            <div class="tipo-card-content">
                <div class="tipo-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <span class="tipo-nombre">Paciente </span>
                <span class="tipo-desc">Traslado de paciente</span>
            </div>
        </label>

        <label class="tipo-card">
            <input type="radio" name="tipo_traslado" value="biologico">
            <div class="tipo-card-content">
                <div class="tipo-icon biologico">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                    </svg>
                </div>
                <span class="tipo-nombre">Material Biológico</span>
                <span class="tipo-desc">Órganos, sangre, muestras clínicas</span>
            </div>
        </label>

        <label class="tipo-card">
            <input type="radio" name="tipo_traslado" value="equipamiento">
            <div class="tipo-card-content">
                <div class="tipo-icon equipamento">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                    </svg>
                </div>
                <span class="tipo-nombre">Equipamiento</span>
                <span class="tipo-desc">Equipos médicos, instrumental</span>
            </div>
        </label>
    </div>

    <div class="step-actions">
        <button type="button" class="btn btn-primary btn-lg" id="btn-step-1" disabled>
            Continuar
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </button>
    </div>
</div>