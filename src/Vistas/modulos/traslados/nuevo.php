<!-- View: New Transfer Form -->
<a href="/traslados" class="back-button">
    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
    </svg>
    Volver a la lista
</a>

<div class="card">
    <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem;">
        Nuevo Traslado
    </h2>

    <!-- Alert: No vehicles -->
    <div class="alert alert-warning">
        <div class="alert-content">
            <svg class="alert-icon" width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span class="alert-text">No se encuentran vehiculos disponibles en este momento</span>
        </div>
        <button class="btn btn-warning btn-small">
            Solicitar SAME
        </button>
    </div>

    <form action="/traslados/guardar" method="POST">

        <!-- Step 1: Transfer Type -->
        <div class="progressive-step completed">
            <div class="form-group">
                <label class="form-label">Elemento a trasladar</label>
                <select class="form-select" name="tipo_traslado">
                    <option value="">Seleccione una opcion</option>
                    <option value="patient" selected>Paciente</option>
                    <option value="equipment">Equipamiento</option>
                </select>
            </div>
        </div>

         <!-- Step 2: Driver -->
        <div class="progressive-step active">
            <div class="form-group">
                <label class="form-label">Chofer</label>
                <select class="form-select" name="chofer">
                    <option value="">Seleccione un chofer</option>
                    <option value="1">Juan Perez</option>
                    <option value="2">Maria Garcia</option>
                    <option value="3">Carlos Lopez</option>
                </select>
                <p class="form-hint">
                    Seleccione el chofer asignado al traslado
                </p>
            </div>
        </div>

        <!-- Step 3: Nurse (disabled until driver selected) -->
        <div class="progressive-step">
            <div class="form-group form-disabled">
                <label class="form-label">Enfermero</label>
                <div class="progressive-hint">
                    Este campo se habilitara despues de seleccionar el chofer
                </div>
            </div>
        </div>

        <!-- Step 4: Additional Details (disabled) -->
        <div class="progressive-step">
            <div class="form-group form-disabled">
                <label class="form-label">Detalles adicionales</label>
                <div class="progressive-hint">
                    Los campos adicionales apareceran secuencialmente al completar los anteriores
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-large">
            Confirmar Traslado
        </button>
    </form>
</div>