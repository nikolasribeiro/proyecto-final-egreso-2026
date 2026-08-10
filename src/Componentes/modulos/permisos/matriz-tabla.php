<?php

/**
 * Tabla de matriz de permisos para UN recurso (#130).
 *
 * Variables esperadas:
 *   - string  $recursoClave : clave del recurso (ej: "traslados")
 *   - string  $recursoLabel : nombre legible del recurso
 *   - array   $matriz       : Roles :: matriz()
 *   - array   $acciones     : Roles :: acciones()
 *   - array   $roles        : Roles :: labels()
 *   - array   $idRoles      : [ui_key => id_rol numérico] resuelto por el controlador
 *   - bool    $puedeEditar  : si el usuario actual puede alternar celdas
 */

$idRolPorUi  = $idRoles ?? [];
$puedeEditar = (bool)($puede_editar ?? false);
?>

<div class="permisos-matriz-wrapper">
    <h3 class="permisos-recurso-titulo"><?= e($recursoLabel ?? $recursoClave ?? 'Recurso') ?></h3>
    <div class="table-container">
        <div class="table-responsive">
            <table class="permisos-matriz-table">
                <thead>
                    <tr>
                        <th class="permisos-col-rol">Rol \ Acción</th>
                        <?php foreach ($acciones as $accionClave => $accionLabel): ?>
                            <th class="permisos-col-accion"><?= e($accionLabel) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $rolClave => $rolLabel): ?>
                        <?php $idRol = (int)($idRolPorUi[$rolClave] ?? 0); ?>
                        <tr>
                            <td class="permisos-col-rol">
                                <span class="permisos-rol-label"><?= e($rolLabel) ?></span>
                            </td>
                            <?php foreach ($acciones as $accionClave => $accionLabel): ?>
                                <?php
                                    $permitido = (bool)($matriz[$rolClave][$recursoClave][$accionClave] ?? false);
                                    // Si no se pudo resolver id_rol o el usuario no
                                    // puede editar, igual dibujamos la celda pero como
                                    // <span> para que el JS no la tome como toggleable.
                                    $esBoton = $puedeEditar && $idRol > 0;
                                ?>
                                <td class="permisos-col-celda">
                                    <?php if ($esBoton): ?>
                                        <button
                                            type="button"
                                            class="permisos-check permisos-check-<?= $permitido ? 'on' : 'off' ?>"
                                            data-permiso-id-rol="<?= e((string)$idRol) ?>"
                                            data-permiso-recurso="<?= e((string)$recursoClave) ?>"
                                            data-permiso-accion="<?= e((string)$accionClave) ?>"
                                            data-permiso-valor="<?= $permitido ? '1' : '0' ?>"
                                            aria-pressed="<?= $permitido ? 'true' : 'false' ?>"
                                            title="<?= $permitido ? 'Permitido (click para denegar)' : 'Denegado (click para permitir)' ?>">
                                            <?= $permitido ? '✓' : '✗' ?>
                                        </button>
                                    <?php else: ?>
                                        <span
                                            class="permisos-check permisos-check-<?= $permitido ? 'on' : 'off' ?> permisos-check-readonly"
                                            title="<?= $permitido ? 'Permitido' : 'Denegado' ?>">
                                            <?= $permitido ? '✓' : '✗' ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>