<?php

/**
 * Alerta de error del formulario de login.
 *
 * Variables disponibles:
 * @var string|null $error_message Mensaje de error (flash desde sesión) a mostrar
 */

$errorMsg = isset($error_message) && $error_message !== null && $error_message !== ''
    ? (string)$error_message
    : '';

// Si hay mensaje, renderizamos la alerta con la clase `show` para que el
// CSS la muestre; si no hay mensaje, la dejamos oculta. El JS del lado
// cliente sigue teniendo su propio mapa de slugs (?error=csrf, etc.)
// como fallback, pero no debe pisar un mensaje ya renderizado por el
// servidor.
$alertClasses = 'auth-alert auth-alert-error' . ($errorMsg !== '' ? ' show' : '');
?>

<div id="auth-alert" class="<?= $alertClasses ?>">
    <svg class="auth-alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    <span id="auth-alert-message"><?= e($errorMsg ?: 'Credenciales inválidas. Por favor, intenta de nuevo.') ?></span>
</div>