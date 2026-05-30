<?php

/**
 * 
 * @var string $errorMsg
 */

$errorMsg = $error_login ?? '';
?>

<div id="auth-alert" class="auth-alert auth-alert-error">
    <svg class="auth-alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    <span id="auth-alert-message"><?= e($errorMsg ?: 'Credenciales inválidas. Por favor, intenta de nuevo.') ?></span>
</div>