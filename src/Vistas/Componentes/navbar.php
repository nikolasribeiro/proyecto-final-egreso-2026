<nav class="navbar">
    <strong>Mi App</strong>
    <a href="/">Inicio</a>
    <a href="/clientes">Pacientes</a>
    <?php if (isset($_SESSION['user'])): ?>
        <a href="/logout">Cerrar Sesión</a>
    <?php else: ?>
        <a href="/login">Iniciar Sesión</a>
    <?php endif; ?>
</nav>