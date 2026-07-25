<?php
/**
 * Vista de login.
 *
 * @var string|null $error
 * @var string $token_csrf
 */
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>HC - <?= e($titulo_pagina ?? 'Iniciar sesión') ?></title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>

<body class="login-body">
    <main class="login-container">
        <header class="login-header">
            <h1 class="login-title">S.I.G.S.M.</h1>
            <p class="login-subtitle">Hospital Clinicas</p>
        </header>

        <?php if (!empty($error)): ?>
            <div class="login-error" role="alert">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/login" class="login-form" autocomplete="on">
            <input type="hidden" name="token_csrf" value="<?= e($token_csrf) ?>">

            <label class="login-label" for="email">Correo electrónico</label>
            <input
                id="email"
                name="email"
                type="email"
                class="login-input"
                autocomplete="username"
                required
                autofocus>

            <label class="login-label" for="password">Contraseña</label>
            <input
                id="password"
                name="password"
                type="password"
                class="login-input"
                autocomplete="current-password"
                required>

            <button type="submit" class="login-button">Iniciar sesión</button>
        </form>

        <footer class="login-footer">
            <p class="login-hint">Demo: <code>admin@demo.com</code> / <code>admin</code> &middot; <code>tecnico@demo.com</code> / <code>tecnico</code></p>
        </footer>
    </main>
</body>

</html>
