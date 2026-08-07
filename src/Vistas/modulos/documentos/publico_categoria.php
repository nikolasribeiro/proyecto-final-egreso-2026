<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentos Clínicos</title>
    <style>
        /* Estilos Mobile-First usando Flexbox y Box Model */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f6f9;
            color: #333;
        }
        .contenedor {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            box-sizing: border-box;
        }
        .cabecera {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #0056b3;
        }
        .cabecera h2 {
            margin: 0 0 10px 0;
            color: #0056b3;
        }
        .tarjeta-doc {
            background: #ffffff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .titulo-doc {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
        }
        .fecha-doc {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .btn-ver {
            background-color: #0056b3;
            color: white;
            text-align: center;
            text-decoration: none;
            padding: 12px;
            border-radius: 6px;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .btn-ver:hover {
            background-color: #004494;
        }
        .mensaje-vacio {
            text-align: center;
            color: #6c757d;
            padding: 40px 20px;
            background: #ffffff;
            border-radius: 8px;
        }
    </style>
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
                    <article class="tarjeta-doc">
                        <div>
                            <h3 class="titulo-doc"><?= htmlspecialchars($doc['titulo']) ?></h3>
                            <span class="fecha-doc">Publicado: <?= date('d/m/Y', strtotime($doc['created_at'])) ?></span>
                        </div>
                        <!-- Enlace al segundo método del controlador público para ver el PDF -->
                        <a href="/d/doc/<?= urlencode($doc['id']) ?>" class="btn-ver">Ver Documento</a>
                    </article>
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