<?php

/**
 * Componente del formulario de encuesta cuantitativa.
 *
 * Variables esperadas:
 *   - array  $plantilla     : Plantilla seleccionada
 *   - array  $plantillas    : Todas las plantillas
 *   - string $plantilla_seleccionada
 */
?>

<form id="encuesta-form" method="POST" action="/dashboard/encuestas" class="encuesta-form">
    <input type="hidden" name="csrf_token" value="<?= e(\Nucleo\Sesion::generarTokenCsrf()) ?>">

    <div class="encuesta-plantilla-selector">
        <label for="encuesta-plantilla" class="form-label">Plantilla de encuesta</label>
        <select id="encuesta-plantilla" name="plantilla" class="form-select"
                onchange="window.location.href = '/dashboard/encuestas?plantilla=' + this.value">
            <?php foreach ($plantillas as $id => $p): ?>
                <option value="<?= e($id) ?>" <?= $id === $plantilla_seleccionada ? 'selected' : '' ?>>
                    <?= e($p['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="encuesta-plantilla-desc">
            <?= e($plantilla['descripcion'] ?? '') ?>
        </p>
    </div>

    <?php foreach ($plantilla['preguntas'] as $index => $pregunta): ?>
        <fieldset class="encuesta-pregunta" data-pregunta-id="<?= e($pregunta['id']) ?>">
            <legend class="encuesta-pregunta-titulo">
                <span class="encuesta-pregunta-numero"><?= $index + 1 ?></span>
                <?= e($pregunta['texto']) ?>
            </legend>

            <div class="encuesta-escala-extremos">
                <span class="encuesta-extremo"><?= e($pregunta['minLabel']) ?></span>
                <span class="encuesta-extremo"><?= e($pregunta['maxLabel']) ?></span>
            </div>

            <div class="encuesta-escala">
                <?php for ($i = 1; $i <= 10; $i++): ?>
                    <?php
                        $idRadio = "p_{$pregunta['id']}_{$i}";
                    ?>
                    <input type="radio"
                           id="<?= e($idRadio) ?>"
                           name="p_<?= e($pregunta['id']) ?>"
                           value="<?= $i ?>"
                           class="encuesta-radio"
                           required>
                    <label for="<?= e($idRadio) ?>" class="encuesta-radio-label" title="<?= $i ?>"><?= $i ?></label>
                <?php endfor; ?>
            </div>
        </fieldset>
    <?php endforeach; ?>

    <div class="encuesta-actions">
        <button type="reset" class="btn btn-outline btn-lg">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Limpiar
        </button>
        <button type="submit" class="btn btn-success btn-lg">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            Enviar Encuesta
        </button>
    </div>
</form>
