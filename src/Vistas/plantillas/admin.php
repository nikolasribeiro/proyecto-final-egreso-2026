<?php 
// Plantilla para el panel administrativo

/**
 * @var string $titulo_pagina
 * @var string $nombre
 * @var string $rol
 * @var string $contenido
 */
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <!-- Meta tag Token CSRF para peticiones Fetch/AJAX (#116) -->
    <meta name="csrf-token" content="<?= e(\Nucleo\Sesion::generarTokenCsrf()) ?>" />
    <title>HC - <?php echo e($titulo_pagina) ?></title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>

<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php componente('admin/sidebar', ['nombre' => $nombre, 'rol' => $rol]); ?>
        <!-- Main Content Wrapper -->
        <div class="main-wrapper">
            <!-- Header -->
            <?php componente('admin/header', ['titulo_pagina' => $titulo_pagina]) ?>
            <!-- Main Content -->
            <main class="main">
                <!-- En contenido es donde se va a inyectar las etiquetas <section> y las diferentes vistas -->
                <?= $contenido ?>
            </main>
        </div>
    </div>

    <script src="/assets/javascript/dashboard/dashboard.js"></script>
</body>

</html>