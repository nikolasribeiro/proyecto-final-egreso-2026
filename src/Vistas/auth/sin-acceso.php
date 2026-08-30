<?php

/**
 * Vista "Sin acceso" — se muestra cuando un usuario logueado no tiene
 * un rol válido asignado. No usa layout: es standalone, sin sidebar,
 * sin header, sin dashboard.js. Reutiliza los estilos de auth para
 * mantener la coherencia visual con el login.
 */

use Nucleo\Constantes\Roles;
?>

<link rel="stylesheet" href="/assets/css/auth/styles.css">

<div class="auth-body">
    <div class="auth-card">
        <div class="auth-alert auth-alert-error show" style="text-align: center; flex-direction: column; align-items: center;">
            <svg class="auth-alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 48px; height: 48px;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
        </div>

        <h1 style="text-align: center; margin-top: 1rem; margin-bottom: 0.5rem; font-size: 1.5rem; color: #1f2937;">
            ¡Felicitaciones por llegar hasta acá!
        </h1>

        <p style="text-align: center; color: #6b7280; margin-bottom: 0.5rem; font-size: 1rem;">
            Pero no hay nada para ver por aquí.
        </p>

        <p style="text-align: center; color: #6b7280; margin-bottom: 1.5rem; font-size: 0.875rem;">
            Usuario sin rol asignado, contacte al administrador para más información.
        </p>

        <a href="/logout" class="auth-submit" style="text-decoration: none; text-align: center; display: inline-block;">
            Volver al inicio de sesión
        </a>
    </div>
</div>
