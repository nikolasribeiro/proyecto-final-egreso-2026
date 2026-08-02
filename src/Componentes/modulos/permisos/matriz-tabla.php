<?php

/**
 * Tabla de matriz de permisos para UN recurso.
 *
 * Variables esperadas:
 *   - string  $recursoClave : clave del recurso (ej: "traslados")
 *   - string  $recursoLabel : nombre legible del recurso
 *   - array   $matriz       : Roles :: matriz()
 *   - array   $acciones     : Roles :: acciones()
 *   - array   $roles        : Roles :: labels()
 */
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
                        <tr>
                            <td class="permisos-col-rol">
                                <span class="permisos-rol-label"><?= e($rolLabel) ?></span>
                            </td>
                            <?php foreach ($acciones as $accionClave => $accionLabel): ?>
                                <td class="permisos-col-celda">
                                    <?php
                                        $permitido = $matriz[$rolClave][$recursoClave][$accionClave] ?? false;
                                    ?>
                                    <?php if ($permitido): ?>
                                        <span class="permisos-check permisos-check-on" title="Permitido">✓</span>
                                    <?php else: ?>
                                        <span class="permisos-check permisos-check-off" title="Denegado">✗</span>
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
