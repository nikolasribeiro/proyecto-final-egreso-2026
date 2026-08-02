<!-- View: Transfer Detail - Rediseño 2 columnas -->
<?php
$td = $traslado_data ?? [];
$destinos = $td['destinos'] ?? [];
$prioridad = $td['prioridad'] ?? 'verde';
$tipo = $td['tipo'] ?? 'paciente_alta';

$prioridadLabel = match ($prioridad) {
    'rojo' => 'EMERGENCIA',
    'amarillo' => 'URGENTE',
    default => 'RUTINARIO',
};

$tipoLabel = match ($tipo) {
    'paciente_alta' => 'Paciente',
    'biologico' => 'Material Biológico',
    'equipamiento' => 'Equipamiento',
    default => 'Traslado',
};

$estadoNormalizado = strtolower($td['estado'] ?? 'pendiente');
$estadoLabel = match ($estadoNormalizado) {
    'pendiente' => 'PENDIENTE',
    'en_transito' => 'EN TRÁNSITO',
    'finalizado' => 'FINALIZADO',
    'cancelado' => 'CANCELADO',
    default => strtoupper($td['estado'] ?? ''),
};
?>

<div id="transfer-detail"
    class="view-section detail-transfer-<?= e($estadoNormalizado) ?>"
    data-traslado-id="<?= (int)($traslado_id ?? 0) ?>"
    data-estado="<?= e($estadoNormalizado) ?>">

    <a class="back-button" href="/dashboard/traslados">
        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Volver a la lista
    </a>

    <!-- ============= HEADER ============= -->
    <header class="detail-header card">
        <div class="detail-header-info">
            <h2>Traslado #<?= e($td['numero'] ?? '') ?></h2>
            <p class="detail-route">
                <strong>Origen:</strong>
                <span><?= e($td['origen'] ?? '-') ?></span>
                <?php if (!empty($destinos)): ?>
                    <svg class="route-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                    <strong>Destino<?= count($destinos) > 1 ? 's' : '' ?>:</strong>
                    <span><?= e(implode(' → ', array_column($destinos, 'nombre'))) ?></span>
                <?php endif; ?>
                <?php if (!empty($td['volver_al_origen'])): ?>
                    <svg class="route-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h11m0 0l-4-4m4 4l-4 4" />
                    </svg>
                    <strong>Regreso a central</strong>
                <?php endif; ?>
            </p>
            <ul class="detail-meta-list">
                <li>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span><?= e($td['conductor'] ?? '-') ?></span>
                </li>
                <li>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                    <span><?= e($td['vehiculo'] ?? '-') ?></span>
                </li>
                <?php if (!empty($td['enfermero'])): ?>
                    <li>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                        <span><?= e($td['enfermero']) ?></span>
                    </li>
                <?php endif; ?>
                <?php if (!empty($td['fecha_salida'])): ?>
                    <li>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Salida: <?= e(date('d/m/Y H:i', strtotime($td['fecha_salida']))) ?></span>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="detail-header-badges">
            <span class="badge badge-priority-<?= e($prioridad) ?>" title="Prioridad del traslado">
                <span class="badge-dot"></span>
                <?= e($prioridadLabel) ?>
            </span>
            <span class="badge badge-tipo" title="Tipo de traslado">
                <?= e($tipoLabel) ?>
            </span>
            <span class="badge badge-estado" title="Estado actual">
                <?= e($estadoLabel) ?>
            </span>
        </div>
    </header>

    <!-- ============= GRID 2 COLUMNAS ============= -->
    <div class="detail-grid">
        <!-- LEFT: Timeline vertical -->
        <aside class="detail-timeline card">
            <h3 class="detail-section-title">Itinerario</h3>

            <?php if (empty($destinos) && empty($td['volver_al_origen'])): ?>
                <div class="detail-empty-state">
                    <p>Este traslado no tiene destinos registrados.</p>
                </div>
            <?php else: ?>
                <ol class="timeline-list" id="timeline-list">
                    <?php foreach ($destinos as $destino):
                        $estadoDest = $destino['estado_destino'] ?? 'PENDIENTE';
                        $itemClass = match ($estadoDest) {
                            'ARRIBADO' => 'done',
                            'EN_TRANSITO' => 'current',
                            default => 'pending',
                        };
                    ?>
                        <li class="timeline-item timeline-item-<?= e($itemClass) ?>"
                            data-orden="<?= (int)($destino['orden'] ?? 1) ?>"
                            data-estado="<?= e($estadoDest) ?>">
                            <div class="timeline-marker">
                                <?php if ($estadoDest === 'ARRIBADO'): ?>
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                <?php else: ?>
                                    <span><?= (int)($destino['orden'] ?? 1) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="timeline-body">
                                <h4><?= e($destino['nombre'] ?? 'Destino') ?></h4>
                                <?php if (!empty($destino['direccion'])): ?>
                                    <p class="timeline-direction"><?= e($destino['direccion']) ?></p>
                                <?php endif; ?>
                                <p class="timeline-time">
                                    <?php if (!empty($destino['fecha_llegada_efectiva'])): ?>
                                        <span class="timeline-time-label">Arribado:</span>
                                        <strong><?= e(date('H:i', strtotime($destino['fecha_llegada_efectiva']))) ?></strong>
                                    <?php elseif (!empty($destino['fecha_llegada_estimada'])): ?>
                                        <span class="timeline-time-label">Estimado:</span>
                                        <strong><?= e(date('H:i', strtotime($destino['fecha_llegada_estimada']))) ?></strong>
                                    <?php endif; ?>
                                </p>
                                <?php if (!empty($destino['reportes'])): ?>
                                    <div class="timeline-reports">
                                        <?php foreach ($destino['reportes'] as $rep): ?>
                                            <div class="report-card">
                                                <div class="report-card-header">
                                                    <span class="report-card-tipo"><?= e($rep['tipo_problema'] ?? 'Incidente') ?></span>
                                                    <time class="report-card-time"><?= e(date('H:i', strtotime($rep['fecha_reporte'] ?? 'now'))) ?></time>
                                                </div>
                                                <p class="report-card-msg"><?= e($rep['mensaje'] ?? '') ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>

                    <?php if (!empty($td['volver_al_origen'])): ?>
                        <li class="timeline-item timeline-item-pending" data-tipo="retorno">
                            <div class="timeline-marker">
                                <span>↩</span>
                            </div>
                            <div class="timeline-body">
                                <h4>Regreso a <?= e($td['origen'] ?? 'Central') ?></h4>
                                <p class="timeline-direction">Hospital de Clínicas - Base</p>
                            </div>
                        </li>
                    <?php endif; ?>
                </ol>
            <?php endif; ?>
        </aside>

        <!-- RIGHT: Action panel -->
        <section class="detail-action-panel card" id="action-panel">
            <h3 class="detail-section-title">Próxima acción</h3>
            <p class="detail-action-desc" id="action-desc">Cargando información…</p>

            <button id="btn-main-action" class="btn btn-primary btn-lg btn-block" disabled>
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span id="action-text">Cargando…</span>
            </button>

            <button id="btn-report" class="btn btn-outline-danger btn-block" disabled>
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                Reportar incidente
            </button>

            <div class="detail-action-helper">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p>El vehículo se considera disponible nuevamente al registrar el arribo al último destino.</p>
            </div>
        </section>
    </div>

    <!-- Hidden inputs para JS -->
    <input type="hidden" id="csrf-token" value="<?= e($csrf ?? '') ?>">
    <input type="hidden" id="traslado-id" value="<?= (int)($traslado_id ?? 0) ?>">

    <!-- Report Modal -->
    <?php componente('modulos/traslados/detalle/report-modal') ?>
</div>

<script src="/assets/javascript/dashboard/detalle-traslado.js"></script>