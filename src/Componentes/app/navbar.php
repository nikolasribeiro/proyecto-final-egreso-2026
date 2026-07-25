<?php
/**
 * @var array|null $usuario
 */
$tokenCsrf = \Nucleo\Sesion::generarTokenCsrf();
?>
<nav class="navbar">
    <strong>Mi App</strong>
    <a href="/">Inicio</a>
    <a href="/clientes">Pacientes</a>
    <?php if (!empty($usuario)): ?>
        <form method="POST" action="/logout" style="display:inline;">
            <input type="hidden" name="token_csrf" value="<?= e($tokenCsrf) ?>">
            <button type="submit" class="link-button">Cerrar Sesión</button>
        </form>
    <?php else: ?>
        <a href="/login">Iniciar Sesión</a>
    <?php endif; ?>
</nav>
