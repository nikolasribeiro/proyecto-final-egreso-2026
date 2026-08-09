<?php

/**
 * Vista de gestión de vehículos — resumen, filtros y listado paginado.
 *
 * Variables disponibles:
 * @var array  $vehiculos        Filas hidratadas por ModeloVehiculo::listar()
 * @var array  $tipos            ModeloVehiculo::obtenerTiposVehiculo()
 * @var array  $filtros          {estado, activo, tipo, q, pagina, por_pagina, total, total_paginas}
 * @var array  $stats_estado     {total, disponibles, no_disponibles}
 * @var bool   $puede_crear      Permiso vehiculos.crear
 * @var bool   $puede_editar     Permiso vehiculos.editar
 * @var bool   $puede_eliminar   Permiso vehiculos.eliminar
 * @var array|null $flash         {tipo, mensaje}
 */

$statsEstado = is_array($stats_estado ?? null) ? $stats_estado : [];
?>
<section id="vehiculos" class="section active vehiculos-page">
    <div class="section-header">
        <div>
            <h2 class="section-title">Gestión de Vehículos</h2>
            <p class="section-description">
                Alta, edición y baja de vehículos. La disponibilidad operativa
                (Disponible / No disponible) se actualiza automáticamente al
                crear, cancelar o finalizar traslados.
            </p>
        </div>
        <?php if ($puede_crear ?? false): ?>
            <a class="btn btn-primary" href="/dashboard/vehiculos/nuevo">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Nuevo Vehículo
            </a>
        <?php endif; ?>
    </div>

    <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= e((string)($flash['tipo'] ?? 'info')) ?>" role="alert">
            <?= e((string)($flash['mensaje'] ?? '')) ?>
        </div>
    <?php endif; ?>

    <section class="stats-row" aria-label="Resumen de vehículos">
        <article class="stat-card stat-card-total">
            <div class="stat-card-content">
                <span class="stat-card-label">Total</span>
                <strong class="stat-card-value">
                    <?= e((string)(int)($statsEstado['total'] ?? 0)) ?>
                </strong>
            </div>
            <span class="stat-card-icon" aria-hidden="true">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                </svg>
            </span>
        </article>

        <article class="stat-card stat-card-disponibles">
            <div class="stat-card-content">
                <span class="stat-card-label">Disponibles</span>
                <strong class="stat-card-value">
                    <?= e((string)(int)($statsEstado['disponibles'] ?? 0)) ?>
                </strong>
            </div>
            <span class="stat-card-icon" aria-hidden="true">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
        </article>

        <article class="stat-card stat-card-no-disponibles">
            <div class="stat-card-content">
                <span class="stat-card-label">No disponibles</span>
                <strong class="stat-card-value">
                    <?= e((string)(int)($statsEstado['no_disponibles'] ?? 0)) ?>
                </strong>
            </div>
            <span class="stat-card-icon" aria-hidden="true">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 12H6m12 0a6 6 0 11-12 0 6 6 0 0112 0z" />
                </svg>
            </span>
        </article>
    </section>

    <?php componente('modulos/vehiculos/filtros', [
        'tipos' => $tipos,
        'filtros' => $filtros,
    ]); ?>

    <?php componente('modulos/vehiculos/tabla', [
        'vehiculos' => $vehiculos,
        'puede_editar' => $puede_editar ?? false,
        'puede_eliminar' => $puede_eliminar ?? false,
    ]); ?>

    <?php componente('modulos/vehiculos/paginacion', [
        'filtros' => $filtros,
    ]); ?>
</section>