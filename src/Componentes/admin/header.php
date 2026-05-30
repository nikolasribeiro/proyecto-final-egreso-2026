<?php

/**
 * Componente Header del panel administrativo
 * 
 * @var string $titulo_pagina Título de la página actual
 */

$titulo = $titulo_pagina ?? 'Dashboard';
?>

<style>
    /* ========== Header ========== */
    .header {
        background: var(--white);
        padding: 0 1.5rem;
        height: var(--header-height, 64px);
        box-shadow: var(--shadow-sm, 0 1px 2px rgba(0, 0, 0, 0.05));
        position: sticky;
        top: 0;
        z-index: 100;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid var(--border-gray, #e5e7eb);
    }

    .header-left {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .hamburger {
        width: 40px;
        height: 40px;
        border: none;
        background: var(--light-gray, #f3f4f6);
        border-radius: var(--radius-sm, 8px);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--secondary-gray, #6b7280);
        transition: all 0.2s ease;
    }

    .hamburger:hover {
        background: var(--border-gray, #e5e7eb);
        color: var(--black, #1f2937);
    }

    @media (min-width: 1024px) {
        .hamburger {
            display: none;
        }
    }

    .header-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--black, #1f2937);
        margin: 0;
    }
</style>


<header class="header">
    <!-- Left: Hamburger + Título -->
    <div class="header-left">
        <button class="hamburger" id="hamburger" onclick="toggleSidebar()" aria-label="Abrir menú">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
        <h1 class="header-title" id="header-title">
            <?= e($titulo) ?>
        </h1>
    </div>

    <!-- Right: Botón Logout -->
    <?php componente('admin/cerrar-sesion/index') ?>
</header>