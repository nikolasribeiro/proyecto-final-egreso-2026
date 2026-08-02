<div class="form-step" id="step-5">
    <h3 class="step-title">
        <span class="step-number">5</span>
        Asignar Personal
    </h3>

    <div class="personal-form">
        <div class="form-group">
            <label for="conductor-input" class="form-label required">Conductor</label>
            <div class="autocomplete-wrapper" id="conductor-wrapper">
                <input
                    type="text"
                    id="conductor-input"
                    class="form-input"
                    autocomplete="off"
                    placeholder="Escribí el nombre o la CI..."
                    required>
                <div class="autocomplete-dropdown" id="conductor-dropdown" hidden role="listbox"></div>
            </div>
            <select id="conductor" name="conductor" hidden required>
                <option value="">—</option>
                <?php foreach (($choferes ?? []) as $c): ?>
                    <option value="<?= (int)$c['ci'] ?>"
                            data-ci="<?= (int)$c['ci'] ?>"
                            data-nombre="<?= e($c['nombre'].' '.$c['apellido']) ?>">
                        <?= e($c['nombre'].' '.$c['apellido']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="step-hint">Tipeá para filtrar. Seleccioná una opción de la lista.</p>
        </div>

        <div class="form-group">
            <label for="enfermero-input" class="form-label">Enfermero <span class="optional">(Opcional)</span></label>
            <div class="autocomplete-wrapper" id="enfermero-wrapper">
                <input
                    type="text"
                    id="enfermero-input"
                    class="form-input"
                    autocomplete="off"
                    placeholder="Escribí el nombre o la CI...">
                <div class="autocomplete-dropdown" id="enfermero-dropdown" hidden role="listbox"></div>
            </div>
            <select id="enfermero" name="enfermero" hidden>
                <option value="">—</option>
                <?php foreach (($enfermeros ?? []) as $e): ?>
                    <option value="<?= (int)$e['ci'] ?>"
                            data-ci="<?= (int)$e['ci'] ?>"
                            data-nombre="<?= e($e['nombre'].' '.$e['apellido']) ?>">
                        <?= e($e['nombre'].' '.$e['apellido']) ?>
                    </option>
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