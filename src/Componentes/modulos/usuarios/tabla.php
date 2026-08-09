<?php

/**
 * Componente: tabla de usuarios con badges por rol y acciones
 * de edición / baja / reactivación.
 *
 * Variables:
 * @var array $usuarios       Filas ya hidratadas por ModeloUsuario::listar()
 * @var array $roles          Roles::labels()
 * @var bool  $puede_editar
 */
?>
<div class="table-container usuarios-table-container">
    <div class="table-responsive">
        <table class="usuarios-table">
            <thead>
                <tr>
                    <th class="usuarios-col-ci" scope="col">CI</th>
                    <th class="usuarios-col-nombre" scope="col">Nombre</th>
                    <th class="usuarios-col-email" scope="col">Email</th>
                    <th class="usuarios-col-roles" scope="col">Roles</th>
                    <th class="usuarios-col-fecha" scope="col">Fecha de Alta</th>
                    <th class="usuarios-col-estado" scope="col">Estado</th>
                    <th class="usuarios-col-acciones" scope="col">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($usuarios)): ?>
                    <tr class="usuarios-empty-row">
                        <td colspan="7" class="usuarios-empty-cell">
                            <div class="empty-state" role="status">
                                <span class="empty-state-icon" aria-hidden="true">
                                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 16l5 5m-2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 10h.01M13 10h.01M8 14c.8.7 1.8 1 3 1s2.2-.3 3-1" />
                                    </svg>
                                </span>
                                <p class="empty-state-message">No hay usuarios que coincidan con los filtros</p>
                                <p class="empty-state-help">Probá cambiar los criterios de búsqueda o limpiar los filtros.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($usuarios as $usuario): ?>
                    <?php
                        $activo = (bool)($usuario['activo'] ?? false);
                        $estadoClase = $activo ? 'status-completed' : 'status-inactive';
                        $estadoTexto = (string)($usuario['estado_legible'] ?? ($activo ? 'Activo' : 'Inactivo'));
                        $rolesUi = is_array($usuario['roles_ui'] ?? null) ? $usuario['roles_ui'] : [];
                        $usuarioId = (int)($usuario['id'] ?? 0);
                        $nombreCompleto = trim(
                            (string)($usuario['nombre'] ?? '') . ' ' . (string)($usuario['apellido'] ?? '')
                        );
                        $fechaAlta = trim((string)($usuario['fecha_alta'] ?? ''));
                        $fechaAltaTimestamp = $fechaAlta !== '' ? strtotime($fechaAlta) : false;
                        $fechaAltaTexto = $fechaAltaTimestamp !== false
                            ? date('d/m/Y', $fechaAltaTimestamp)
                            : ($fechaAlta !== '' ? $fechaAlta : '—');
                        $confirmacionBaja = json_encode(
                            '¿Dar de baja a ' . $nombreCompleto . ' (CI ' . (string)($usuario['ci'] ?? '') . ')?',
                            JSON_UNESCAPED_UNICODE
                                | JSON_HEX_TAG
                                | JSON_HEX_AMP
                                | JSON_HEX_APOS
                                | JSON_HEX_QUOT
                        );
                    ?>
                    <tr class="usuarios-row" data-estado="<?= e($activo ? 'activo' : 'inactivo') ?>">
                        <td data-label="CI">
                            <span class="usuario-ci"><?= e((string)($usuario['ci'] ?? '')) ?></span>
                        </td>
                        <td data-label="Nombre">
                            <span class="usuario-nombre"><?= e($nombreCompleto) ?></span>
                        </td>
                        <td data-label="Email">
                            <span class="usuario-email"><?= e((string)($usuario['email'] ?? '')) ?></span>
                        </td>
                        <td data-label="Roles">
                            <?php if (empty($rolesUi)): ?>
                                <span class="rol-badge rol-badge-empty">Sin roles</span>
                            <?php else: ?>
                                <div class="usuarios-roles-cell">
                                    <?php foreach ($rolesUi as $rolClave): ?>
                                        <?php $rolClave = (string)$rolClave; ?>
                                        <span class="rol-badge rol-badge-rol-<?= e($rolClave) ?>">
                                            <?= e((string)($roles[$rolClave] ?? $rolClave)) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Fecha Alta">
                            <?php if ($fechaAltaTimestamp !== false): ?>
                                <time class="usuario-fecha" datetime="<?= e($fechaAlta) ?>">
                                    <?= e($fechaAltaTexto) ?>
                                </time>
                            <?php else: ?>
                                <span class="usuario-fecha"><?= e($fechaAltaTexto) ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Estado">
                            <span class="transfer-status usuarios-status <?= e($estadoClase) ?>">
                                <span class="status-dot"></span>
                                <?= e($estadoTexto) ?>
                            </span>
                        </td>
                        <td data-label="Acciones">
                            <?php if ($puede_editar): ?>
                                <div class="usuario-acciones">
                                    <a
                                        href="<?= e('/dashboard/usuarios/' . $usuarioId . '/editar') ?>"
                                        class="btn btn-outline btn-small"
                                        aria-label="Editar usuario <?= e($nombreCompleto) ?>">
                                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        Editar
                                    </a>

                                    <?php if ($activo): ?>
                                        <form
                                            method="POST"
                                            action="<?= e('/dashboard/usuarios/' . $usuarioId . '/baja') ?>"
                                            class="usuario-accion-form"
                                            onsubmit="return confirm(<?= e((string)$confirmacionBaja) ?>);">
                                            <input type="hidden" name="csrf_token" value="<?= e(\Nucleo\Sesion::generarTokenCsrf()) ?>">
                                            <button type="submit" class="btn btn-danger btn-small">
                                                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                </svg>
                                                Baja
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form
                                            method="POST"
                                            action="<?= e('/dashboard/usuarios/' . $usuarioId . '/reactivar') ?>"
                                            class="usuario-accion-form">
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
                                <span class="usuario-sin-acciones" aria-label="Sin acciones disponibles">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
