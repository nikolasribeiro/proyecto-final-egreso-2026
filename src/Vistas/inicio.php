<?php

/**
 * @var string $nombreCliente
 */
?>

<div class="welcome-container">
    <h1>¡Bienvenido al Micro Framework!</h1>
    <p>Hola Mundo desde PHP, sin haber instalado librerías externas.</p>

    <div class="patient-card">
        <strong>Último Paciente Registrado:</strong>
        <span class="patient-name">
            <?= e($nombreCliente ?? "No existe este paciente") ?>
        </span>
    </div>
</div>