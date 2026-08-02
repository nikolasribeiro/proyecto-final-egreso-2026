<div class="form-step" id="step-5">
    <h3 class="step-title">
        <span class="step-number">5</span>
        Asignar Personal
    </h3>

    <div class="personal-form">
        <div class="form-group">
            <label for="conductor" class="form-label required">Conductor</label>
            <select id="conductor" name="conductor" class="form-select" required>
                <option value="">Seleccionar conductor...</option>
                <?php
                $conductores = [
                    ['id' => 1, 'nombre' => 'Juan Pérez', 'cedula' => '1.234.567-8'],
                    ['id' => 2, 'nombre' => 'Carlos Rodríguez', 'cedula' => '2.345.678-9'],
                    ['id' => 3, 'nombre' => 'María García', 'cedula' => '3.456.789-0'],
                ];
                foreach ($conductores as $conductor):
                ?>
                    <option value="<?= $conductor['id'] ?>"><?= $conductor['nombre'] ?> (<?= $conductor['cedula'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="enfermero" class="form-label">Enfermero <span class="optional">(Opcional)</span></label>
            <select id="enfermero" name="enfermero" class="form-select">
                <option value="">Sin enfermero</option>
                <?php
                $enfermeros = [
                    ['id' => 1, 'nombre' => 'Ana Martínez', 'cedula' => '4.567.890-1'],
                    ['id' => 2, 'nombre' => 'Roberto López', 'cedula' => '5.678.901-2'],
                    ['id' => 3, 'nombre' => 'Laura Fernández', 'cedula' => '6.789.012-3'],
                ];
                foreach ($enfermeros as $enfermero):
                ?>
                    <option value="<?= $enfermero['id'] ?>"><?= $enfermero['nombre'] ?> (<?= $enfermero['cedula'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Jerarquía de enfermería: se muestra solo si se eligió un enfermero (JS) -->
        <div class="form-group" id="jerarquia-enfermero-group" style="display: none;">
            <label for="jerarquia-enfermero" class="form-label">Jerarquía de Enfermería</label>
            <select id="jerarquia-enfermero" name="jerarquia_enfermero" class="form-select">
                <option value="">-- Sin jerarquía específica --</option>
                <option value="licenciado">Licenciado en Enfermería</option>
                <option value="auxiliar">Auxiliar de Enfermería</option>
                <option value="profesional">Enfermero Profesional</option>
            </select>
            <p class="step-hint">Requerido por auditoría ESRE para documentar los actores del sistema.</p>
        </div>
    </div>

    <div class="step-actions">
        <button type="button" class="btn btn-outline btn-lg" id="btn-back-5">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Volver
        </button>
        <button type="button" class="btn btn-primary btn-lg" id="btn-step-5" disabled>
            Continuar
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </button>
    </div>
</div>