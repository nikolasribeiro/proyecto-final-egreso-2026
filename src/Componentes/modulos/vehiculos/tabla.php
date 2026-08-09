<?php

/**
 * Componente: tabla de vehículos con badges por estado/activo y acciones
 * de edición / baja / reactivación.
 *
 * Variables:
 * @var array $vehiculos       Filas ya hidratadas por ModeloVehiculo::listar()
 * @var bool  $puede_editar
 * @var bool  $puede_eliminar
 */
?>
<div class="table-container vehiculos-table-container">
    <div class="table-responsive">
        <table class="vehiculos-table">
            <thead>
                <tr>
                    <th class="vehiculos-col-matricula" scope="col">Matrícula</th>
                    <th class="vehiculos-col-tipo" scope="col">Tipo</th>
                    <th class="vehiculos-col-estado" scope="col">Estado operativo</th>
                    <th class="vehiculos-col-activo" scope="col">Alta/Baja</th>
                    <th class="vehiculos-col-acciones" scope="col">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($vehiculos)): ?>
                    <tr class="vehiculos-empty-row">
                        <td colspan="5" class="vehiculos-empty-cell">
                            <div class="empty-state" role="status">
                                <span class="empty-state-icon" aria-hidden="true">
                                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 16l5 5m-2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 10h.01M13 10h.01M8 14c.8.7 1.8 1 3 1s2.2-.3 3-1" />
                                    </svg>
                                </span>
                                <p class="empty-state-message">No hay vehículos que coincidan con los filtros</p>
                                <p class="empty-state-help">Probá cambiar los criterios de búsqueda o limpiar los filtros.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($vehiculos as $vehiculo): ?>
                    <?php
                        $activo = (bool)($vehiculo['activo'] ?? false);
                        $estado = (string)($vehiculo['estado'] ?? 'NO-DISPONIBLE');
                        $vehiculoId = (int)($vehiculo['id'] ?? 0);
                        $matricula = (string)($vehiculo['matricula'] ?? '');
                        $tipoDescripcion = (string)($vehiculo['tipo_vehiculo'] ?? '—');
                        $estadoLegible = (string)($vehiculo['estado_legible'] ?? $estado);
                        $estadoClase = $estado === 'DISPONIBLE' ? 'status-completed' : 'status-inactive';
                        $activoLegible = $activo ? 'Activo' : 'De baja';
                        $activoClase = $activo ? 'status-completed' : 'status-inactive';
                        $confirmacionBaja = json_encode(
                            '¿Dar de baja al vehículo ' . $matricula . '?',
                            JSON_UNESCAPED_UNICODE
                                | JSON_HEX_TAG
                                | JSON_HEX_AMP
                                | JSON_HEX_APOS
                                | JSON_HEX_QUOT
                        );
                        $confirmacionReactivar = json_encode(
                            '¿Reactivar el vehículo ' . $matricula . '?',
                            JSON_UNESCAPED_UNICODE
                                | JSON_HEX_TAG
                                | JSON_HEX_AMP
                                | JSON_HEX_APOS
                                | JSON_HEX_QUOT
                        );
                    ?>
                    <tr class="vehiculos-row" data-activo="<?= $activo ? 'activo' : 'inactivo' ?>" data-estado="<?= e(strtolower($estado)) ?>">
                        <td data-label="Matrícula">
                            <span class="vehiculo-matricula"><?= e($matricula) ?></span>
                        </td>
                        <td data-label="Tipo">
                            <span class="vehiculo-tipo"><?= e($tipoDescripcion) ?></span>
                        </td>
                        <td data-label="Estado operativo">
                            <span class="transfer-status vehiculos-status <?= e($estadoClase) ?>">
                                <span class="status-dot"></span>
                                <?= e($estadoLegible) ?>
                            </span>
                        </td>
                        <td data-label="Alta/Baja">
                            <span class="transfer-status vehiculos-status <?= e($activoClase) ?>">
                                <span class="status-dot"></span>
                                <?= e($activoLegible) ?>
                            </span>
                        </td>
                        <td data-label="Acciones">
                            <?php if ($puede_editar || $puede_eliminar): ?>
                                <div class="vehiculo-acciones">
                                    <?php if ($puede_editar): ?>
                                        <a
                                            href="<?= e('/dashboard/vehiculos/' . $vehiculoId . '/editar') ?>"
                                            class="btn btn-outline btn-small"
                                            aria-label="Editar vehículo <?= e($matricula) ?>">
                                            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            Editar
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($activo && $puede_eliminar): ?>
                                        <form
                                            method="POST"
                                            action="<?= e('/dashboard/vehiculos/' . $vehiculoId . '/baja') ?>"
                                            class="vehiculo-accion-form"
                                            onsubmit="return confirm(<?= e((string)$confirmacionBaja) ?>);">
                                            <input type="hidden" name="csrf_token" value="<?= e(\Nucleo\Sesion::generarTokenCsrf()) ?>">
                                            <button type="submit" class="btn btn-danger btn-small">
                                                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                </svg>
                                                Baja
                                            </button>
                                        </form>
                                    <?php elseif (!$activo && $puede_editar): ?>
                                        <form
                                            method="POST"
                                            action="<?= e('/dashboard/vehiculos/' . $vehiculoId . '/reactivar') ?>"
                                            class="vehiculo-accion-form"
                                            onsubmit="return confirm(<?= e((string)$confirmacionReactivar) ?>);">
                                            <input type="hidden" name="csrf_token" value="<?= e(\Nucleo\Sesion::generarTokenCsrf()) ?>">
                                            <button type="submit" class="btn btn-success btn-small">
                                                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                Reactivar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="vehiculo-sin-acciones" aria-label="Sin acciones disponibles">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>