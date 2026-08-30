<?php
/**
 * Vista de resultados de la encuesta.
 * @var array $encuesta
 * @var array $resultados
 */
?>
<?php
/**
 * Vista de resultados de la encuesta.
 */
?>
<section class="section active">
    <div class="section-header">
        <div>
            <h2 class="section-title">Análisis de Resultados: Encuesta #<?= htmlspecialchars((string)$encuesta['id']) ?></h2>
            <p class="section-description">
                Público Objetivo: <strong><?= htmlspecialchars((string)$encuesta['segmento_dirigido']) ?></strong> 
                (<?= $encuesta['es_anonima'] ? 'Anónima' : 'Identificada' ?>)
            </p>
        </div>
        <a href="/dashboard/encuestas" class="btn btn-secondary">Volver al listado</a>
    </div>

    <?php if ($resultados['total_respuestas'] === 0): ?>
        <div class="card" style="padding: 40px; text-align: center; margin-top: 20px;">
            <h3 style="color: #666;">Aún no hay datos registrados</h3>
            <p style="color: #888;">Comparte el código QR para que los pacientes comiencen a evaluar el servicio.</p>
        </div>
    <?php else: ?>
        <!-- Tarjetas de Resumen -->
        <div style="display: flex; gap: 20px; margin-top: 20px; margin-bottom: 20px;">
            <div class="card" style="flex: 1; padding: 20px; text-align: center; border-left: 4px solid #0056b3;">
                <h3 style="margin: 0; color: #555; font-size: 1rem;">Total de Respuestas</h3>
                <p style="font-size: 2.5rem; font-weight: bold; margin: 10px 0; color: #333;"><?= $resultados['total_respuestas'] ?></p>
            </div>
            <div class="card" style="flex: 1; padding: 20px; text-align: center; border-left: 4px solid #28a745;">
                <h3 style="margin: 0; color: #555; font-size: 1rem;">Calificación Promedio (1 a 10)</h3>
                <p style="font-size: 2.5rem; font-weight: bold; margin: 10px 0; color: #28a745;"><?= $resultados['promedio_general'] ?></p>
            </div>
        </div>

        <!-- Desglose por Preguntas -->
        <div class="card" style="padding: 20px;">
            <h3 style="border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 20px;">Desglose por Pregunta</h3>
            
            <?php foreach ($resultados['detalles'] as $detalle): ?>
                <?php 
                    $numPregunta = $detalle['numero_pregunta'];
                    $promedio = round((float)$detalle['promedio'], 1);
                    $porcentaje = ($promedio / 10) * 100;
                    $color = $promedio >= 7 ? '#28a745' : ($promedio >= 5 ? '#ffc107' : '#dc3545');
                ?>
                <div style="margin-bottom: 25px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <?php 
                            if ($encuesta['id_plantilla'] === 'personalizada') {
                                $preguntasArray = json_decode($encuesta['preguntas'] ?? '[]', true) ?: [];
                                $textoPregunta = $preguntasArray[$numPregunta - 1] ?? "Pregunta " . $numPregunta;
                            } else {
                                $plantillaOficial = \Nucleo\Constantes\PlantillasEncuestas::obtener($encuesta['id_plantilla']);
                                $textoPregunta = $plantillaOficial['preguntas'][$numPregunta - 1]['texto'] ?? "Pregunta " . $numPregunta;
                            }
                        ?>
                        <strong style="font-size: 1.05rem;"><?= htmlspecialchars((string)$textoPregunta) ?></strong>
                        <span style="font-weight: bold; color: <?= $color ?>;"><?= $promedio ?> / 10</span>
                    </div>
                    
                    <div style="background-color: #e9ecef; border-radius: 5px; overflow: hidden; height: 15px; margin-bottom: 8px;">
                        <div style="width: <?= $porcentaje ?>%; background-color: <?= $color ?>; height: 100%; transition: width 0.5s;"></div>
                    </div>

                    <!-- NUEVO: Desglose de votos exactos -->
                    <div style="font-size: 0.85rem; color: #555; background: #f8f9fa; padding: 5px 10px; border-radius: 4px; display: flex; flex-wrap: wrap; gap: 10px;">
                        <span style="color: #888; font-weight: bold;">Distribución de votos:</span>
                        <?php for($i = 1; $i <= 10; $i++): ?>
                            <?php $cantidad = $resultados['distribucion'][$numPregunta][$i] ?? 0; ?>
                            <?php if($cantidad > 0): ?>
                                <span>
                                    [<strong style="color: <?= $i <= 4 ? '#dc3545' : ($i <= 7 ? '#ffc107' : '#28a745') ?>;"><?= $i ?></strong>]: 
                                    <?= $cantidad ?> <?= $cantidad === 1 ? 'voto' : 'votos' ?>
                                </span>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>