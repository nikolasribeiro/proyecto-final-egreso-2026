<!-- Report Modal - Report an issue during transfer -->
<div id="report-modal" class="modal-overlay" data-modal="report">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Reportar Incidente</h3>
            <button class="modal-close" aria-label="Cerrar">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form id="report-form" class="modal-body">
            <div class="form-group" style="text-align: left; margin-bottom: var(--space-4);">
                <label for="reporte-tipo" class="form-label">¿Qué sucedió?</label>
                <select id="reporte-tipo" name="tipo_problema" class="form-select" required>
                    <option value="">Seleccionar tipo de incidente</option>
                    <option value="Daño mecánico">Daño mecánico</option>
                    <option value="Accidente de tráfico">Accidente de tráfico</option>
                    <option value="Condiciones climáticas">Condiciones climáticas</option>
                    <option value="Retraso en ruta">Retraso en ruta</option>
                    <option value="Problema con el paciente">Problema con el paciente</option>
                    <option value="Otro">Otro</option>
                </select>
            </div>

            <div class="form-group" style="text-align: left;">
                <label for="reporte-mensaje" class="form-label">Descripción del incidente</label>
                <textarea
                    id="reporte-mensaje"
                    name="mensaje"
                    class="form-textarea"
                    rows="4"
                    placeholder="Describa brevemente qué ocurrió..."
                    required></textarea>
            </div>
        </form>

        <div class="modal-footer" style="flex-direction: column; gap: var(--space-3);">
            <button type="submit" form="report-form" class="btn btn-primary" style="width: 100%;">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Ingresar Reporte
            </button>
            <button type="button" id="btn-cancelar-traslado" class="btn btn-danger" style="width: 100%;">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                Cancelar Traslado
            </button>
        </div>
    </div>
</div>
