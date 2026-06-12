<?php

/**
 * Vista de la matriz de permisos (ESRE).
 *
 * @var array $matriz
 * @var array $recursos
 * @var array $acciones
 * @var array $roles
 */
?>

<section id="permisos" class="section active">
    <div class="section-header">
        <div>
            <h2 class="section-title">Matriz de Permisos</h2>
            <p class="section-description">
                Configuración de privilegios por rol para cada recurso del sistema.
            </p>
        </div>
    </div>

    <div class="alert alert-info permisos-info">
        <strong>Leyenda:</strong>
        <span class="permisos-check permisos-check-on">✓</span> Permitido
        &nbsp;&nbsp;
        <span class="permisos-check permisos-check-off">✗</span> Denegado
    </div>

    <div class="permisos-recursos-grid">
        <?php foreach ($recursos as $recursoClave => $recursoLabel): ?>
            <?php
                componente('modulos/permisos/matriz-tabla', [
                    'matriz' => $matriz,
                    'acciones' => $acciones,
                    'roles' => $roles,
                    'recursoClave' => $recursoClave,
                    'recursoLabel' => $recursoLabel,
                ]);
            ?>
        <?php endforeach; ?>
    </div>
</section>
