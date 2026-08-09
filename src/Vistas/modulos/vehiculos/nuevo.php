<?php

/**
 * Vista: alta de un vehículo nuevo.
 *
 * Variables:
 * @var string $nombre
 * @var string $rol
 * @var array  $tipos          Catálogo de tipos de vehículo
 * @var string $csrf           Token CSRF (ya generado por el controlador)
 * @var array|null $flash      {tipo, mensaje}
 */
?>
<section id="vehiculos-nuevo" class="section active">
    <div class="section-header">
        <div>
            <h2 class="section-title">Nuevo Vehículo</h2>
            <p class="section-description">
                Alta de vehículo nuevo. La matrícula debe ser única y el tipo
                define para qué clase de traslados queda habilitado.
            </p>
        </div>
        <a href="/dashboard/vehiculos" class="btn btn-outline btn-small">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Volver al listado
        </a>
    </div>

    <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= e((string)($flash['tipo'] ?? 'info')) ?>" role="alert">
            <?= e((string)($flash['mensaje'] ?? '')) ?>
        </div>
    <?php endif; ?>

    <?php componente('modulos/vehiculos/form-vehiculo', [
        'accion_post' => '/dashboard/vehiculos',
        'csrf' => $csrf,
        'texto_boton' => 'Crear Vehículo',
        'tipos' => $tipos,
        'valores' => [],
    ]); ?>
</section>