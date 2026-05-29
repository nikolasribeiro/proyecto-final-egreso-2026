<?php

/**
 * Plantilla por defecto
 *
 * @var string $titulo
 * @var string $contenido
 */
?>


<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo ?? 'Micro Framework UTU' ?></title>
    <link rel="stylesheet" href="/assets/css/globals.app.css">
</head>

<body>
    <!-- 
    Esto es un componente. Sirve para extraer y abstraer componentes comunes 
    que se repiten en varias vistas.
    -->
    <?php componente('app/navbar'); ?>

    <main class="container">
        <!-- Aquí se inyecta dinámicamente la vista renderizada por Nucleo\Vista -->
        <?= $contenido ?>
    </main>

    <footer class="footer">
        &copy; <?= date('Y') ?> Proyecto UTU - Todos los derechos reservados.
    </footer>
</body>

</html>