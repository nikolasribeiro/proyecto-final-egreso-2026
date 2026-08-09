<?php

/**
 * Vista: edición de un vehículo existente.
 *
 * Variables:
 * @var string $nombre
 * @var string $rol
 * @var array  $vehiculo       Fila hidratada por ModeloVehiculo::buscarPorId()
 * @var array  $tipos          Catálogo de tipos de vehículo
 * @var string $csrf           Token CSRF (ya generado por el controlador)
 * @var array|null $flash
 */
?>
<section id="vehiculos-editar" class="section active">
    <div class="section-header">
        <div>
            <h2 class="section-title">Editar Vehículo #<?= (int)($vehiculo['id'] ?? 0) ?></h2>
            <p class="section-description">
                Modificá la matrícula y/o el tipo. La disponibilidad operativa
                se gestiona desde el listado (baja/reactivar) y automáticamente
                con cada traslado.
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
        'accion_post' => '/dashboard/vehiculos/' . (int)($vehiculo['id'] ?? 0),
        'csrf' => $csrf,
        'texto_boton' => 'Guardar Cambios',
        'tipos' => $tipos,
        'valores' => $vehiculo,
    ]); ?>
</section>