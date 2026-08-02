<?php

/**
 * Vista del módulo de encuestas.
 *
 * @var array  $plantillas
 * @var array  $plantilla
 * @var string $plantilla_seleccionada
 * @var array  $flash
 */
?>

<section id="encuestas" class="section active">
    <div class="section-header">
        <div>
            <h2 class="section-title">Encuestas de Satisfacción</h2>
            <p class="section-description">
                Mida cuantitativamente la calidad del servicio brindado.
            </p>
        </div>
    </div>

    <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= e($flash['tipo'] ?? 'info') ?>" role="alert">
            <?= e($flash['mensaje'] ?? '') ?>
        </div>
    <?php endif; ?>

    <div class="card encuesta-card">
        <?php
            componente('modulos/encuestas/formulario', [
                'plantilla' => $encuesta,
                'plantillas' => $plantillas,
                'plantilla_seleccionada' => $plantilla_seleccionada,
            ]);
        ?>
    </div>
</section>

<script src="/assets/javascript/dashboard/encuestas.js"></script>
