<?php

/**
 * Plantilla de autenticación - Login
 * 
 * Variables disponibles:
 * @var string|null $csrf_token Token CSRF para el formulario
 * @var string|null $error_message Mensaje de error a mostrar
 */

$csrfToken = $csrf_token ?? '';
$errorMsg = $error_message ?? '';
?>

<link rel="stylesheet" href="/assets/css/auth/styles.css">

<div class="auth-body">
    <div class="auth-card">
        <!-- Logo -->
        <?php componente('auth/login/logo') ?>

        <!-- Alerta de error -->
        <?php componente('auth/login/alerta-error', [$errorMsg])  ?>

        <!-- Formulario -->
        <form id="auth-form" class="auth-form" action="/login" method="POST" novalidate>
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <!-- Usuario -->
            <div class="auth-form-group">
                <label for="auth-username" class="auth-label">Usuario</label>
                <div class="auth-input-wrapper">
                    <input
                        type="text"
                        id="auth-username"
                        name="username"
                        class="auth-input"
                        placeholder="Ingresá tu usuario"
                        autocomplete="username"
                        required
                        minlength="3">
                    <svg class="auth-input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span class="auth-validation-icon" id="auth-username-valid">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </span>
                </div>
                <div class="auth-error-message" id="auth-username-error">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>El usuario debe tener al menos 3 caracteres</span>
                </div>
            </div>

            <!-- Contraseña -->
            <div class="auth-form-group">
                <label for="auth-password" class="auth-label">Contraseña</label>
                <div class="auth-input-wrapper">
                    <input
                        type="password"
                        id="auth-password"
                        name="password"
                        class="auth-input"
                        placeholder="Ingresá tu contraseña"
                        autocomplete="current-password"
                        required
                        minlength="6">
                    <svg class="auth-input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <button type="button" class="auth-password-toggle" id="auth-toggle-password" aria-label="Mostrar contraseña">
                        <svg id="auth-eye-open" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg id="auth-eye-closed" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                        </svg>
                    </button>
                </div>
                <div class="auth-error-message" id="auth-password-error">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>La contraseña debe tener al menos 6 caracteres</span>
                </div>
            </div>

            <!-- Submit -->
            <button type="submit" id="auth-submit" class="auth-submit" disabled>
                <span class="auth-btn-text">Iniciar Sesión</span>
                <span class="auth-spinner"></span>
            </button>

            <!-- Olvidaste contraseña -->
            <div class="auth-link">
                <a href="/recuperar">¿Olvidaste tu contraseña?</a>
            </div>
        </form>

        <!-- Footer -->
        <?php componente('auth/login/login-footer') ?>
    </div>
</div>

<script src="/assets/javascript/auth/login.js"></script>