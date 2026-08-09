<?php
/**
 * Componente: stepper visual del wizard de nueva solicitud de traslado.
 *
 * Cada item es un `<button>` con `data-step="N"` para que el JS
 * pueda navegar al hacer click (solo pasos completados o el actual
 * son interactivos; los futuros se renderizan deshabilitados).
 */
?>
<div class="transfer-stepper" role="navigation" aria-label="Pasos del wizard">
    <button type="button" class="stepper-item" data-step="1" aria-label="Ir al paso 1: Tipo">
        <div class="stepper-bubble">1</div>
        <span class="stepper-label">Tipo</span>
    </button>
    <div class="stepper-line"></div>
    <button type="button" class="stepper-item" data-step="2" aria-label="Ir al paso 2: Datos Clínicos">
        <div class="stepper-bubble">2</div>
        <span class="stepper-label">Datos Clínicos</span>
    </button>
    <div class="stepper-line"></div>
    <button type="button" class="stepper-item" data-step="3" aria-label="Ir al paso 3: Origen">
        <div class="stepper-bubble">3</div>
        <span class="stepper-label">Origen</span>
    </button>
    <div class="stepper-line"></div>
    <button type="button" class="stepper-item" data-step="4" aria-label="Ir al paso 4: Destinos">
        <div class="stepper-bubble">4</div>
        <span class="stepper-label">Destinos</span>
    </button>
    <div class="stepper-line"></div>
    <button type="button" class="stepper-item" data-step="5" aria-label="Ir al paso 5: Personal">
        <div class="stepper-bubble">5</div>
        <span class="stepper-label">Personal</span>
    </button>
    <div class="stepper-line"></div>
    <button type="button" class="stepper-item" data-step="6" aria-label="Ir al paso 6: Vehículo">
        <div class="stepper-bubble">6</div>
        <span class="stepper-label">Vehículo</span>
    </button>
    <div class="stepper-line"></div>
    <button type="button" class="stepper-item" data-step="7" aria-label="Ir al paso 7: Confirmar">
        <div class="stepper-bubble">7</div>
        <span class="stepper-label">Confirmar</span>
    </button>
</div>