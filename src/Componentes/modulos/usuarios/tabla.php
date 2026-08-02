<?php

/**
 * Tabla de usuarios con acciones de baja/reactivar.
 *
 * @var array $usuarios
 * @var array $roles
 */
?>

<div class="table-container">
    <div class="table-responsive">
        <table class="usuarios-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Nombre</th>
                    <th>Rol</th>
                    <th>Email</th>
                    <th>Fecha de Alta</th>
                    <th>Fecha de Baja</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $usuario): ?>
                    <?php
                        $activo = empty($usuario['fecha_baja']);
                        $estadoClase = $activo ? 'status-completed' : 'status-inactive';
                        $estadoTexto = $activo ? 'Activo' : 'Inactivo';
                        $rolLabel = $roles[$usuario['rol']] ?? $usuario['rol'];
                    ?>
                    <tr data-estado="<?= $activo ? 'activo' : 'inactivo' ?>">
                        <td data-label="Username">
                            <span class="usuario-username"><?= e($usuario['username']) ?></span>
                        </td>
                        <td data-label="Nombre"><?= e($usuario['nombre']) ?></td>
                        <td data-label="Rol"><?= e($rolLabel) ?></td>
                        <td data-label="Email"><?= e($usuario['email']) ?></td>
                        <td data-label="Fecha Alta"><?= e($usuario['fecha_alta']) ?></td>
                        <td data-label="Fecha Baja"><?= e($usuario['fecha_baja'] ?? '—') ?></td>
                        <td data-label="Estado">
                            <span class="transfer-status <?= $estadoClase ?>">
                                <span class="status-dot"></span>
                                <?= e($estadoTexto) ?>
                            </span>
                        </td>
                        <td data-label="Acciones">
                            <?php if ($activo): ?>
                                <form method="POST"
                                      action="/dashboard/usuarios/<?= e($usuario['username']) ?>/baja"
                                      style="display:inline"
                                      onsubmit="return confirm('¿Dar de baja a <?= e($usuario['nombre']) ?>?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(\Nucleo\Sesion::generarTokenCsrf()) ?>">
                                    <button type="submit" class="btn btn-danger btn-small">
                                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                        </svg>
                                        Dar de Baja
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="POST"
                                      action="/dashboard/usuarios/<?= e($usuario['username']) ?>/reactivar"
                                      style="display:inline">
                                    <input type="hidden" name="csrf_token" value="<?= e(\Nucleo\Sesion::generarTokenCsrf()) ?>">
                                    <button type="submit" class="btn btn-success btn-small">
                                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Reactivar
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
