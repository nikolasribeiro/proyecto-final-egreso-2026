<?php

/**
 * @var array  $traslados
 * @var bool   $puede_crear
 * @var string $filtro_actual
 * @var array  $prioridades_activas
 */

$filtroActual = $filtro_actual ?? 'todos';
$prioridadesActivas = $prioridades_activas ?? ['verde', 'amarillo', 'rojo'];

$prioridadInfo = [
    'verde'    => ['label' => 'Rutinario',  'clase' => 'filtro-prio-verde'],
    'amarillo' => ['label' => 'Urgente',     'clase' => 'filtro-prio-amarillo'],
    'rojo'     => ['label' => 'Emergencia',  'clase' => 'filtro-prio-rojo'],
];
?>

<section id="traslados" class="section active">
    <!-- View: Transfer List -->
    <div id="transfer-list" class="view-section">
        <div class="section-header">
            <div>
                <h2 class="section-title">Traslados</h2>
                <p class="section-description">
                    Gestione y monitoree los traslados
                </p>
            </div>
            <?php if (!empty($puede_crear)): ?>
                <a
                    class="btn btn-primary"
                    href="/dashboard/traslados/nuevo">
                    <svg
                        class="icon"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 4v16m8-8H4" />
                    </svg>
                    Solicitar Traslado
                </a>
            <?php endif; ?>
        </div>

        <form method="get" action="/dashboard/traslados" id="traslados-filtros-form" class="traslados-filtros">
            <div class="traslados-toolbar">
                <h3 class="traslados-toolbar-title">Listado</h3>
                <select id="filtro-estado" name="filtro" class="form-select" onchange="this.form.submit()">
                    <option value="todos" <?= $filtroActual === 'todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="activos" <?= $filtroActual === 'activos' ? 'selected' : '' ?>>Activos</option>
                    <option value="completados" <?= $filtroActual === 'completados' ? 'selected' : '' ?>>Completados o cancelados</option>
                </select>
            </div>

            <div class="traslados-prioridades" role="group" aria-label="Filtrar por prioridad">
                <?php foreach ($prioridadInfo as $key => $info):
                    $activo = in_array($key, $prioridadesActivas, true);
                ?>
                    <button
                        type="button"
                        class="filtro-chip <?= e($info['clase']) ?><?= $activo ? ' active' : '' ?>"
                        data-prio="<?= e($key) ?>"
                        aria-pressed="<?= $activo ? 'true' : 'false' ?>">
                        <span class="priority-dot"></span>
                        <?= e($info['label']) ?>
                    </button>
                <?php endforeach; ?>
                <input type="hidden" name="prioridades" id="prioridades-input" value="<?= e(implode(',', $prioridadesActivas)) ?>">
            </div>
        </form>

        <script>
          (function () {
            const form = document.getElementById("traslados-filtros-form");
            const input = document.getElementById("prioridades-input");
            if (!form || !input) return;

            form.querySelectorAll(".filtro-chip").forEach((chip) => {
              chip.addEventListener("click", () => {
                const pressed = chip.getAttribute("aria-pressed") === "true";
                chip.setAttribute("aria-pressed", pressed ? "false" : "true");
                chip.classList.toggle("active", !pressed);

                const activas = Array.from(
                  form.querySelectorAll('.filtro-chip[aria-pressed="true"]'),
                ).map((c) => c.dataset.prio);
                input.value = activas.join(",");
                form.submit();
              });
            });
          })();
        </script>

        <hr class="traslados-divider">

        <?php if (empty($traslados)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                    </svg>
                </div>
                <h3 class="empty-title">No hay traslados para mostrar</h3>
                <p class="empty-text">
                    <?= $filtroActual === 'todos'
                        ? 'Aún no se han solicitado traslados.'
                        : 'No hay traslados que coincidan con el filtro seleccionado.' ?>
                </p>
            </div>
        <?php else: ?>
            <div class="card-grid">
                <?php foreach ($traslados as $traslado): ?>
                    <?php
                    $prioridadClase = match ($traslado['prioridad_interna'] ?? 'verde') {
                        'rojo'     => 'badge-priority-red',
                        'amarillo' => 'badge-priority-yellow',
                        default    => 'badge-priority-green',
                    };
                    ?>
                    <a
                        class="card transfer-card"
                        href="/dashboard/traslados/<?= $traslado['id'] ?>"
                        style="cursor: pointer">
                        <div class="transfer-header">
                            <span class="transfer-type-badge badge-patient"><?= e($traslado['tipo']) ?></span>
                            <span class="transfer-priority-badge <?= $prioridadClase ?>" title="Prioridad: <?= e($traslado['prioridad']) ?>">
                                <span class="priority-dot"></span>
                                <?= e($traslado['prioridad']) ?>
                            </span>
                            <span class="transfer-id">#<?= e($traslado['id']) ?></span>
                        </div>
                        <div class="transfer-details">
                            <div class="transfer-detail-row">
                                <svg
                                    class="icon"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <?= e($traslado['ubicacion_origen'] ?? '') ?> - <?= e($traslado['ubicacion_destino'] ?? '') ?>
                            </div>
                            <div class="transfer-detail-row">
                                <svg
                                    class="icon"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Chofer: <?= e($traslado['chofer'] ?? '') ?>
                            </div>
                        </div>
                        <div class="transfer-status <?= e($traslado['estado_clase_css'] ?? '') ?>">
                            <span class="status-dot"></span>
                            <?= e($traslado['estado'] ?? '') ?>
                        </div>
                    </a>
                <?php endforeach; ?>

            </div>
        <?php endif; ?>
    </div>
</section>