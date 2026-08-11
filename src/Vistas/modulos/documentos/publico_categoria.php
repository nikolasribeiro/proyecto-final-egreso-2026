<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentos Clínicos</title>
</head>
<body>
    <div class="contenedor">
        <header class="cabecera">
            <h2>Documentos Disponibles</h2>
            <p>Categoría: <strong><?= htmlspecialchars(ucfirst(str_replace('-', ' ', $slug))) ?></strong></p>
        </header>

        <main>
            <?php if (!empty($documentos)): ?>
                <?php foreach ($documentos as $doc): ?>
                   <?php componente('modulos/documentos/article', [
                        'titulo' => $doc['titulo'],
                        'created_at' => $doc['created_at'],
                        'id' => $doc['id']
                   ]) ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="mensaje-vacio">
                    <p>No hay documentos disponibles en esta categoría por el momento.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>