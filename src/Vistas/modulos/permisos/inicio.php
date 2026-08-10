<?php

/**
 * Vista de la matriz de permisos (ESRE).
 *
 * @var array $matriz
 * @var array $recursos
 * @var array $acciones
 * @var array $roles
 * @var array $idRoles
 * @var bool  $puede_editar
 * @var string $csrf_token
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
        <?php if (!$puede_editar): ?>
            &nbsp;&nbsp;<em>(solo lectura — tu rol no puede editar la matriz)</em>
        <?php endif; ?>
    </div>

    <div
        class="permisos-recursos-grid"
        data-permisos-csrf="<?= e($csrf_token ?? '') ?>"
        data-permisos-puede-editar="<?= $puede_editar ? '1' : '0' ?>">
        <?php foreach ($recursos as $recursoClave => $recursoLabel): ?>
            <?php
                componente('modulos/permisos/matriz-tabla', [
                    'matriz' => $matriz,
                    'acciones' => $acciones,
                    'roles' => $roles,
                    'idRoles' => $idRoles,
                    'puede_editar' => $puede_editar,
                    'recursoClave' => $recursoClave,
                    'recursoLabel' => $recursoLabel,
                ]);
            ?>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($puede_editar): ?>
    <script src="/assets/javascript/dashboard/permisos.js" defer></script>
<?php endif; ?>
