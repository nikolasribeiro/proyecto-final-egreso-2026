<?php 
// Plantilla para el panel administrativo

/**
 * @var string $title
 * @var string $contenido
 */
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Panel de Administración' ?></title>
</head>

<body>
    <aside class="sidebar">
        <h2 style="margin-top: 0; font-size: 1.25rem; padding-left: 1rem; color: white;">Panel Admin</h2>
        <a href="/admin/dashboard">Dashboard</a>
        <a href="/admin/users">Gestión de Usuarios</a>
        <a href="/admin/settings">Configuraciones</a>
        <a href="/">&larr; Volver a la App</a>
    </aside>

    <div class="content-area">
        <header class="header">
            <h1 style="margin: 0; font-size: 1.5rem;">Administración Segura</h1>
        </header>

        <main>
            <!-- Aquí se inyecta el contenido de las vistas que usen el layout 'admin' -->
            <?= $contenido ?>
        </main>
    </div>
</body>

</html>