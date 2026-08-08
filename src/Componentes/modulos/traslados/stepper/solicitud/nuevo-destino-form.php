<?php

/**
 * Componente: formulario inline para crear una ubicación (destino) nueva.
 *
 * Se usa dentro del modal de selección de destinos del wizard de traslados,
 * para el caso en que el destino buscado todavía no existe en la base.
 *
 * Parámetros:
 *   - id_prefijo (string) prefijo para los ids del bloque. Permite montar el
 *                 componente más de una vez en la misma página sin colisiones.
 *                 Por defecto "nuevo-destino".
 *   - texto_boton (string) etiqueta del botón que despliega el formulario.
 */

$prefijo = $id_prefijo ?? 'nuevo-destino';
$textoBoton = $texto_boton ?? '¿No encontrás el destino? Crear uno nuevo';
?>
<div class="modal-nuevo-destino" data-nuevo-destino data-prefijo="<?= e($prefijo) ?>">
    <button type="button" class="btn btn-outline btn-block" id="btn-<?= e($prefijo) ?>" data-rol="toggle">
        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        <?= e($textoBoton) ?>
    </button>

    <div class="form-nuevo-destino" id="form-<?= e($prefijo) ?>" data-rol="form" hidden>
        <label class="form-label" for="<?= e($prefijo) ?>-nombre">Nombre del lugar</label>
        <input type="text" id="<?= e($prefijo) ?>-nombre" data-rol="nombre" class="form-input" maxlength="150"
            placeholder="Ej: Sanatorio Americano">

        <label class="form-label" for="<?= e($prefijo) ?>-direccion">Dirección</label>
        <input type="text" id="<?= e($prefijo) ?>-direccion" data-rol="direccion" class="form-input" maxlength="255"
            placeholder="Ej: Av. Italia 2364, Montevideo">

        <p class="form-error" id="<?= e($prefijo) ?>-error" data-rol="error" hidden></p>

        <div class="form-nuevo-destino-actions">
            <button type="button" class="btn btn-outline" data-rol="cancelar">Cancelar</button>
            <button type="button" class="btn btn-primary" data-rol="guardar">Guardar y agregar</button>
        </div>
    </div>
</div>
