<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encuesta de Satisfacción - S.I.G.S.M.</title>
    <link rel="stylesheet" href="/assets/css/pages/encuesta-mobile.css">
</head>
<body>
    <div class="survey-container">
        <header class="survey-header">
            <div class="hospital-badge">S.I.G.S.M. - Hospital de Clínicas</div>
            <h1>Encuesta de Satisfacción</h1>
            <?php if (isset($encuesta['es_anonima']) && $encuesta['es_anonima']): ?>
                <div class="badge-anonimo">🔒 Encuesta 100% Anónima</div>
            <?php endif; ?>
            <p>Ayúdenos a mejorar evaluando nuestro servicio (del 1 al 10, donde 10 es Excelente).</p>
        </header>

        <form action="/encuesta/<?= htmlspecialchars($encuesta['token_publico'] ?? '') ?>/enviar" method="POST" class="survey-form">
            
            <?php if (!empty($encuesta['preguntas'])): ?>
                <?php foreach ($encuesta['preguntas'] as $index => $pregunta): ?>
                    <div class="form-group">
                        <label><?= ($index + 1) . '. ' . e($pregunta['texto'] ?? 'Pregunta sin texto') ?></label>
                        <input type="number" 
                               name="p_<?= $index + 1 ?>"
                               min="1" 
                               max="10" 
                               required 
                               placeholder="1 a 10">
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="form-group">
                    <p style="color: #ef4444; text-align: center;">No hay preguntas configuradas para esta encuesta.</p>
                </div>
            <?php endif; ?>

            <hr>
            
            <?php if (!empty($encuesta['preguntas'])): ?>
                <button type="submit" class="btn-submit">Enviar Respuestas</button>
            <?php endif; ?>
        </form>
    </div>
</body>
</html>