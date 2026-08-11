<?php

/**
 * Vista de cambio de contraseña obligatorio (#40).
 * Se muestra cuando el usuario logueado tiene debe_cambiar_password = TRUE
 * (caso típico: usuario root recién creado con password 'root').
 *
 * Variables disponibles:
 * @var string|null $csrf_token
 * @var string|null $error_message
 * @var array       $usuario  Sesión actual del usuario
 */

$csrfToken = $csrf_token ?? '';
$errorMsg  = $error_message ?? '';
$nombre    = (string)($usuario['nombre'] ?? '');
?>

<link rel="stylesheet" href="/assets/css/auth/styles.css">

<div class="auth-body">
    <div class="auth-card">
        <?php componente('auth/login/logo') ?>

        <?php componente('auth/login/alerta-error', [$errorMsg]) ?>

        <h2 class="auth-title">Cambiar contraseña</h2>
        <p class="auth-subtitle">
            <?php if ($nombre !== ''): ?>
                Hola <strong><?= e($nombre) ?></strong>, tu contraseña es temporal.
            <?php else: ?>
                Tu contraseña es temporal.
            <?php endif; ?>
            Definí una nueva para continuar.
        </p>

        <form id="cambiar-password-form" class="auth-form"
              action="/cambiar-password" method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <!-- Contraseña actual -->
            <div class="auth-form-group">
                <label for="auth-password-actual" class="auth-label">Contraseña actual</label>
                <input
                    type="password"
                    id="auth-password-actual"
                    name="password_actual"
                    class="auth-input"
                    placeholder="Tu contraseña temporal"
                    autocomplete="current-password"
                    required>
            </div>

            <!-- Contraseña nueva -->
            <div class="auth-form-group">
                <label for="auth-password-nueva" class="auth-label">Contraseña nueva</label>
                <input
                    type="password"
                    id="auth-password-nueva"
                    name="password_nueva"
                    class="auth-input"
                    placeholder="Mínimo 8 caracteres"
                    autocomplete="new-password"
                    required
                    minlength="8">
            </div>

            <!-- Confirmación -->
            <div class="auth-form-group">
                <label for="auth-password-confirma" class="auth-label">Confirmar contraseña nueva</label>
                <input
                    type="password"
                    id="auth-password-confirma"
                    name="password_confirma"
                    class="auth-input"
                    placeholder="Repetí la contraseña nueva"
                    autocomplete="new-password"
                    required
                    minlength="8">
            </div>

            <button type="submit" id="auth-submit" class="auth-submit">
                <span class="auth-btn-text">Guardar y continuar</span>
            </button>
        </form>
    </div>
</div>

<script>
    // Validación mínima client-side: que coincidan las dos contraseñas
    // y que difieran de la actual. El server valida de vuelta.
    (function () {
        const form = document.getElementById('cambiar-password-form');
        if (!form) return;
        form.addEventListener('submit', function (ev) {
            const actual  = document.getElementById('auth-password-actual').value;
            const nueva   = document.getElementById('auth-password-nueva').value;
            const confirma = document.getElementById('auth-password-confirma').value;
            if (nueva.length < 8) {
                ev.preventDefault();
                alert('La nueva contraseña debe tener al menos 8 caracteres.');
                return;
            }
            if (nueva !== confirma) {
                ev.preventDefault();
                alert('La confirmación no coincide con la nueva contraseña.');
                return;
            }
            if (nueva === actual) {
                ev.preventDefault();
                alert('La nueva contraseña debe ser distinta de la actual.');
                return;
            }
        });
    })();
</script>